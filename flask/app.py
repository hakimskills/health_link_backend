from flask import Flask, request, jsonify
import os
import logging
import numpy as np
import faiss
from werkzeug.utils import secure_filename
from model.feature_extractor import extract_features
import mysql.connector
import json

app = Flask(__name__)

# Database configuration (match Laravel's .env)
DB_CONFIG = {
    'host': '127.0.0.1',
    'database': 'health_link',
    'user': 'root',
    'password': '',
    'port': 3306
}

# Configure logging
logging.basicConfig(filename='flask.log', level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')

# Directories
UPLOAD_FOLDER = 'Uploads'
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

# Database connection
def get_db_connection():
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        logging.info('Database connection established')
        return connection
    except Exception as e:
        logging.error('Database connection failed: %s', str(e))
        return None

@app.route('/extract-features', methods=['POST'])
def extract():
    logging.info('Received request to /extract-features')
    image_files = request.files.getlist('images')
    image_ids = request.form.getlist('image_ids[]')

    if not image_files or len(image_files) != len(image_ids):
        logging.error('Invalid request: %s images, %s IDs', len(image_files), len(image_ids))
        return jsonify({'error': 'Number of images and IDs must match'}), 400

    connection = get_db_connection()
    if connection is None:
        logging.error('Database connection failed')
        return jsonify({'error': 'Database connection failed'}), 500

    cursor = connection.cursor()
    features_map = {}
    try:
        for file, image_id in zip(image_files, image_ids):
            if file.filename == '':
                logging.warning('Skipping empty filename for image ID: %s', image_id)
                continue
            filename = secure_filename(file.filename)
            filepath = os.path.join(UPLOAD_FOLDER, filename)
            file.save(filepath)

            try:
                try:
                    image_id_int = int(image_id)
                except ValueError:
                    logging.error('Invalid image_id format: %s', image_id)
                    return jsonify({'error': f'Invalid image_id format: {image_id}'}), 400

                cursor.execute("SELECT id FROM product_images WHERE id = %s", (image_id_int,))
                if not cursor.fetchone():
                    logging.error('Invalid image_id: %s not found in product_images', image_id)
                    return jsonify({'error': f'Image ID {image_id} not found in product_images'}), 400

                features = extract_features(filepath)
                if features is None or not isinstance(features, np.ndarray):
                    logging.error('Invalid features for image ID: %s', image_id)
                    return jsonify({'error': f'Invalid features for image ID {image_id}'}), 500

                feature_vector = json.dumps(features.tolist())
                cursor.execute("""
                    INSERT INTO image_features (image_id, feature_vector, created_at, updated_at)
                    VALUES (%s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE feature_vector = %s, updated_at = NOW()
                """, (image_id_int, feature_vector, feature_vector))
                connection.commit()
                features_map[image_id] = features.tolist()
                logging.info('Saved features for image ID: %s', image_id)
            except mysql.connector.Error as sql_err:
                logging.error('SQL error for image ID %s: %s', image_id, str(sql_err))
                connection.rollback()
                return jsonify({'error': f'SQL error for image ID {image_id}: {str(sql_err)}'}), 500
            except Exception as e:
                logging.error('Failed to extract features for image ID %s: %s', image_id, str(e))
                connection.rollback()
                return jsonify({'error': f'Failed to extract features for image ID {image_id}: {str(e)}'}), 500
            finally:
                if os.path.exists(filepath):
                    os.remove(filepath)
                    logging.info('Cleaned up file: %s', filepath)
    finally:
        cursor.close()
        connection.close()

    if not features_map:
        logging.warning('No features extracted')
        return jsonify({'error': 'No features extracted'}), 400

    logging.info('Feature extraction completed: %s', features_map.keys())
    return jsonify({'features': features_map})

# Initialize FAISS index from image_features table
def initialize_faiss_index():
    logging.info('Starting FAISS index initialization from image_features')
    connection = get_db_connection()
    if connection is None:
        logging.error('Cannot initialize FAISS: Database connection failed')
        return None, None

    cursor = connection.cursor()
    try:
        cursor.execute("SELECT image_id, feature_vector FROM image_features")
        features = cursor.fetchall()
        if not features:
            logging.warning('No features found in image_features table')
            return None, None

        feature_list = []
        image_ids = []
        for image_id, feature_vector in features:
            try:
                # Validate image_id is numeric
                if not isinstance(image_id, (int, np.integer)):
                    logging.error('Non-numeric image_id found: %s', image_id)
                    continue

                features_array = np.array(json.loads(feature_vector), dtype='float32')
                if features_array.ndim != 1 or features_array.size == 0:
                    logging.error('Invalid feature dimension for image_id %s: %s', image_id, features_array.shape)
                    continue
                feature_list.append(features_array)
                image_ids.append(image_id)
                logging.info('Loaded features for image_id %s', image_id)
            except Exception as e:
                logging.error('Failed to process features for image_id %s: %s', image_id, str(e))
                continue

        if not feature_list:
            logging.error('No valid features loaded from database')
            return None, None

        feature_array = np.array(feature_list)
        logging.info('Feature array shape: %s', feature_array.shape)
        dimension = feature_array.shape[1]
        logging.info('Creating FAISS index with dimension %d', dimension)
        index = faiss.IndexFlatL2(dimension)
        index.add(feature_array)
        logging.info('FAISS index initialized with %d vectors', index.ntotal)
        return index, image_ids
    except Exception as e:
        logging.error('Failed to initialize FAISS index: %s', str(e))
        return None, None
    finally:
        cursor.close()
        connection.close()

# Global variables for FAISS index and image IDs
logging.info('Initializing FAISS index at startup')
index, image_ids = initialize_faiss_index()
logging.info('FAISS initialization complete: index=%s, image_ids=%s', index, image_ids)

@app.route('/rebuild-index', methods=['POST'])
def rebuild_index():
    global index, image_ids
    logging.info('Received request to /rebuild-index')
    new_index, new_image_ids = initialize_faiss_index()
    if new_index is None:
        logging.error('Failed to rebuild FAISS index')
        return jsonify({'error': 'Failed to rebuild FAISS index'}), 500
    index, image_ids = new_index, new_image_ids
    logging.info('FAISS index rebuilt with %d vectors', index.ntotal)
    return jsonify({'message': 'FAISS index rebuilt successfully', 'vector_count': index.ntotal})

@app.route('/search', methods=['POST'])
def search():
    logging.info('Received request to /search')
    if 'image' not in request.files:
        logging.error('No image file provided')
        return jsonify({'error': 'No image file provided'}), 400

    file = request.files['image']
    if file.filename == '':
        logging.error('Empty filename provided')
        return jsonify({'error': 'Empty filename provided'}), 400

    filename = secure_filename(file.filename)
    filepath = os.path.join(UPLOAD_FOLDER, filename)
    file.save(filepath)

    try:
        logging.info('Extracting features for query image: %s', filepath)
        query_vector = extract_features(filepath)
        if query_vector is None or not isinstance(query_vector, np.ndarray):
            logging.error('Invalid query features')
            return jsonify({'error': 'Invalid query features'}), 400

        query_vector = query_vector.reshape(1, -1).astype('float32')
        logging.info('Query vector shape: %s', query_vector.shape)

        if index is None or index.ntotal == 0 or not image_ids:
            logging.error('FAISS index not initialized or empty')
            return jsonify({'error': 'FAISS index not available'}), 500

        k = min(10, index.ntotal)  # Return up to 10 matches
        logging.info('Searching FAISS index for %d nearest neighbors', k)
        distances, indices = index.search(query_vector, k)
        matches = []
        for j, i in enumerate(indices[0]):
            if i >= 0 and i < len(image_ids):
                try:
                    image_id = int(image_ids[i])
                    matches.append({'image_id': image_id, 'distance': float(distances[0][j])})
                except ValueError:
                    logging.error('Invalid image_id at index %d: %s', i, image_ids[i])
                    continue
        logging.info('Search completed: found %d matches: %s', len(matches), matches)
        return jsonify({'matches': matches})
    except Exception as e:
        logging.error('Search failed: %s', str(e))
        return jsonify({'error': str(e)}), 500
    finally:
        if os.path.exists(filepath):
            os.remove(filepath)
            logging.info('Cleaned up file: %s', filepath)

if __name__ == '__main__':
    logging.info('Starting Flask app')
    try:
        app.run(debug=False, port=5000)
    except Exception as e:
        logging.error('Flask app failed to start: %s', str(e))
        raise