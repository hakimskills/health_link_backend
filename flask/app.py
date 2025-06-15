import pandas as pd
import numpy as np
import logging
import os
import json
import faiss
import mysql.connector
from flask import Flask, request, jsonify
from sqlalchemy import create_engine
from werkzeug.utils import secure_filename
from recommendation_model import hybrid_recommendation
from model.feature_extractor import extract_features

app = Flask(__name__)

# Configure logging
logging.basicConfig(filename='flask.log', level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger(__name__)

# Database configuration
DB_USER = 'root'
DB_PASSWORD = ''
DB_HOST = '127.0.0.1'
DB_NAME = 'health_link3'
DB_PORT = 3306
SQLALCHEMY_ENGINE = create_engine(f'mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}/{DB_NAME}')
DB_CONFIG = {
    'host': DB_HOST,
    'database': DB_NAME,
    'user': DB_USER,
    'password': DB_PASSWORD,
    'port': DB_PORT
}

# Directories
UPLOAD_FOLDER = 'Uploads'
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

# Global variables for FAISS index and image IDs
index = None
image_ids = None

def get_db_connection():
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        logger.info('Database connection established')
        return connection
    except Exception as e:
        logger.error('Database connection failed: %s', str(e))
        return None

# Initialize datasets for recommendations
try:
    logger.info("Loading datasets from database...")
    users = pd.read_sql("SELECT id AS user_id, first_name, last_name, email, role FROM users", SQLALCHEMY_ENGINE)
    products = pd.read_sql("SELECT product_id, product_name AS name, description, category, COALESCE(average_rating, 0) AS rating, price FROM products", SQLALCHEMY_ENGINE)
    purchases = pd.read_sql("SELECT buyer_id AS user_id, product_id FROM product_orders po JOIN product_order_items poi ON po.product_order_id = poi.product_order_id", SQLALCHEMY_ENGINE)
    browsing_history = pd.read_sql("SELECT user_id, product_id FROM browsing_history", SQLALCHEMY_ENGINE)
    logger.info("Datasets loaded successfully.")

    required_product_cols = ['product_id', 'name', 'description', 'rating', 'category']
    required_purchase_cols = ['user_id', 'product_id']
    required_browsing_cols = ['user_id', 'product_id']
    dataframes = {
        "products": products,
        "purchases": purchases,
        "browsing_history": browsing_history
    }
    for cols, file in [(required_product_cols, "products"), 
                       (required_purchase_cols, "purchases"),
                       (required_browsing_cols, "browsing_history")]:
        if not all(col in dataframes[file].columns for col in cols):
            raise ValueError(f"{file} missing required columns: {cols}")

    num_users = users["user_id"].nunique()
    num_products = products["product_id"].nunique()
    logger.info(f"Number of unique users: {num_users}, Number of unique products: {num_products}")
    for df, name in [(purchases, "purchases"), (browsing_history, "browsing_history")]:
        if df['product_id'].max() > num_products:
            invalid_products = df[df['product_id'] > num_products]['product_id'].unique()
            raise ValueError(f"{name} contains invalid product_id values: {invalid_products}")
        if df['user_id'].max() > num_users:
            invalid_users = df[df['user_id'] > num_users]['user_id'].unique()
            raise ValueError(f"{name} contains invalid user_id values: {invalid_users}")

except Exception as e:
    logger.error(f"Error during initialization: {str(e)}")
    raise

# Initialize FAISS index from image_features table
def initialize_faiss_index():
    logger.info('Starting FAISS index initialization from image_features')
    connection = get_db_connection()
    if connection is None:
        logger.error('Cannot initialize FAISS: Database connection failed')
        return None, None

    cursor = connection.cursor()
    try:
        cursor.execute("SELECT image_id, feature_vector FROM image_features")
        features = cursor.fetchall()
        if not features:
            logger.warning('No features found in image_features table')
            return None, None

        feature_list = []
        image_ids = []
        for image_id, feature_vector in features:
            try:
                if not isinstance(image_id, (int, np.integer)):
                    logger.error('Non-numeric image_id found: %s', image_id)
                    continue
                features_array = np.array(json.loads(feature_vector), dtype='float32')
                if features_array.ndim != 1 or features_array.size == 0:
                    logger.error('Invalid feature dimension for image_id %s: %s', image_id, features_array.shape)
                    continue
                feature_list.append(features_array)
                image_ids.append(image_id)
                logger.info('Loaded features for image_id %s', image_id)
            except Exception as e:
                logger.error('Failed to process features for image_id %s: %s', image_id, str(e))
                continue

        if not feature_list:
            logger.error('No valid features loaded from database')
            return None, None

        feature_array = np.array(feature_list)
        logger.info('Feature array shape: %s', feature_array.shape)
        dimension = feature_array.shape[1]
        logger.info('Creating FAISS index with dimension %d', dimension)
        index = faiss.IndexFlatL2(dimension)
        index.add(feature_array)
        logger.info('FAISS index initialized with %d vectors', index.ntotal)
        return index, image_ids
    except Exception as e:
        logger.error('Failed to initialize FAISS index: %s', str(e))
        return None, None
    finally:
        cursor.close()
        connection.close()

# Initialize FAISS index at startup
logger.info('Initializing FAISS index at startup')
index, image_ids = initialize_faiss_index()
logger.info('FAISS initialization complete: index=%s, image_ids=%s', index, image_ids)

@app.route('/recommend', methods=['GET'])
def get_recommendations():
    try:
        user_id = request.args.get('user_id', type=int)
        if not user_id:
            return jsonify({'error': 'Missing user_id'}), 400

        logger.debug(f"Processing hybrid recommendation for user_id: {user_id}")

        if user_id not in users['user_id'].values:
            return jsonify({'error': 'User ID not found'}), 404

        purchased_product_ids = purchases[purchases['user_id'] == user_id]['product_id'].unique()
        browsed_product_ids = browsing_history[browsing_history['user_id'] == user_id]['product_id'].unique()
        interacted_products = products[products['product_id'].isin(purchased_product_ids) | 
                                      products['product_id'].isin(browsed_product_ids)].copy()
        interacted_products['source'] = interacted_products['product_id'].apply(
            lambda x: 'Purchased' if x in purchased_product_ids else 'Browsed'
        )

        logger.debug(f"Interacted products: {interacted_products['product_id'].tolist()}")

        recommendations = hybrid_recommendation(user_id, purchases, browsing_history, products)
        recommended_products = recommendations[~recommendations['product_id'].isin(purchased_product_ids)].copy()
        recommended_products = recommended_products.sort_values(by='score', ascending=False)

        if recommended_products.empty:
            return jsonify({'error': 'No recommendations available for this user'}), 404

        response_data = {
            'interacted_products': interacted_products.to_dict(orient='records'),
            'recommended_products': recommended_products.to_dict(orient='records')
        }

        logger.debug(f"Sending response: {response_data}")
        return jsonify(response_data), 200

    except ValueError as e:
        logger.error(f"Invalid input: {str(e)}")
        return jsonify({'error': 'Invalid user_id'}), 400
    except Exception as e:
        logger.error(f"Error in get_recommendations: {str(e)}")
        return jsonify({'error': str(e)}), 500

@app.route('/extract-features', methods=['POST'])
def extract():
    logger.info('Received request to /extract-features')
    image_files = request.files.getlist('images')
    image_ids = request.form.getlist('image_ids[]')

    if not image_files or len(image_files) != len(image_ids):
        logger.error('Invalid request: %s images, %s IDs', len(image_files), len(image_ids))
        return jsonify({'error': 'Number of images and IDs must match'}), 400

    connection = get_db_connection()
    if connection is None:
        logger.error('Database connection failed')
        return jsonify({'error': 'Database connection failed'}), 500

    cursor = connection.cursor()
    features_map = {}
    try:
        for file, image_id in zip(image_files, image_ids):
            if file.filename == '':
                logger.warning('Skipping empty filename for image ID: %s', image_id)
                continue
            filename = secure_filename(file.filename)
            filepath = os.path.join(UPLOAD_FOLDER, filename)
            file.save(filepath)

            try:
                image_id_int = int(image_id)
                cursor.execute("SELECT id FROM product_images WHERE id = %s", (image_id_int,))
                if not cursor.fetchone():
                    logger.error('Invalid image_id: %s not found in product_images', image_id)
                    return jsonify({'error': f'Image ID {image_id} not found in product_images'}), 400

                features = extract_features(filepath)
                if features is None or not isinstance(features, np.ndarray):
                    logger.error('Invalid features for image ID: %s', image_id)
                    return jsonify({'error': f'Invalid features for image ID {image_id}'}), 500

                feature_vector = json.dumps(features.tolist())
                cursor.execute("""
                    INSERT INTO image_features (image_id, feature_vector, created_at, updated_at)
                    VALUES (%s, %s, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE feature_vector = %s, updated_at = NOW()
                """, (image_id_int, feature_vector, feature_vector))
                connection.commit()
                features_map[image_id] = features.tolist()
                logger.info('Saved features for image ID: %s', image_id)
            except mysql.connector.Error as sql_err:
                logger.error('SQL error for image ID %s: %s', image_id, str(sql_err))
                connection.rollback()
                return jsonify({'error': f'SQL error for image ID {image_id}: {str(sql_err)}'}), 500
            except Exception as e:
                logger.error('Failed to extract features for image ID %s: %s', image_id, str(e))
                connection.rollback()
                return jsonify({'error': f'Failed to extract features for image ID {image_id}: {str(e)}'}), 500
            finally:
                if os.path.exists(filepath):
                    os.remove(filepath)
                    logger.info('Cleaned up file: %s', filepath)
    finally:
        cursor.close()
        connection.close()

    if not features_map:
        logger.warning('No features extracted')
        return jsonify({'error': 'No features extracted'}), 400

    logger.info('Feature extraction completed: %s', features_map.keys())
    return jsonify({'features': features_map})

@app.route('/rebuild-index', methods=['POST'])
def rebuild_index():
    global index, image_ids
    logger.info('Received request to /rebuild-index')
    new_index, new_image_ids = initialize_faiss_index()
    if new_index is None:
        logger.error('Failed to rebuild FAISS index')
        return jsonify({'error': 'Failed to rebuild FAISS index'}), 500
    index, image_ids = new_index, new_image_ids
    logger.info('FAISS index rebuilt with %d vectors', index.ntotal)
    return jsonify({'message': 'FAISS index rebuilt successfully', 'vector_count': index.ntotal})

@app.route('/search', methods=['POST'])
def search():
    logger.info('Received request to /search')
    if 'image' not in request.files:
        logger.error('No image file provided')
        return jsonify({'error': 'No image file provided'}), 400

    file = request.files['image']
    if file.filename == '':
        logger.error('Empty filename provided')
        return jsonify({'error': 'Empty filename provided'}), 400

    filename = secure_filename(file.filename)
    filepath = os.path.join(UPLOAD_FOLDER, filename)
    file.save(filepath)

    try:
        logger.info('Extracting features for query image: %s', filepath)
        query_vector = extract_features(filepath)
        if query_vector is None or not isinstance(query_vector, np.ndarray):
            logger.error('Invalid query features')
            return jsonify({'error': 'Invalid query features'}), 400

        query_vector = query_vector.reshape(1, -1).astype('float32')
        logger.info('Query vector shape: %s', query_vector.shape)

        if index is None or index.ntotal == 0 or not image_ids:
            logger.error('FAISS index not initialized or empty')
            return jsonify({'error': 'FAISS index not available'}), 500

        k = min(10, index.ntotal)
        logger.info('Searching FAISS index for %d nearest neighbors', k)
        distances, indices = index.search(query_vector, k)
        matches = []
        for j, i in enumerate(indices[0]):
            if i >= 0 and i < len(image_ids):
                try:
                    image_id = int(image_ids[i])
                    matches.append({'image_id': image_id, 'distance': float(distances[0][j])})
                except ValueError:
                    logger.error('Invalid image_id at index %d: %s', i, image_ids[i])
                    continue
        logger.info('Search completed: found %d matches: %s', len(matches), matches)
        return jsonify({'matches': matches})
    except Exception as e:
        logger.error('Search failed: %s', str(e))
        return jsonify({'error': str(e)}), 500
    finally:
        if os.path.exists(filepath):
            os.remove(filepath)
            logger.info('Cleaned up file: %s', filepath)

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    logger.info('Starting Flask app on port %d', port)
    try:
        app.run(host='0.0.0.0', port=port, debug=False)
    except Exception as e:
        logger.error('Flask app failed to start: %s', str(e))
        raise