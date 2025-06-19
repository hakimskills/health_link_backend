from flask import Flask, request, jsonify
import os
import logging
import numpy as np
import pandas as pd
import faiss
import json
import math
from werkzeug.utils import secure_filename
from model.feature_extractor import extract_features
from recommendation_model import hybrid_recommendation
import mysql.connector
from sqlalchemy import create_engine

app = Flask(__name__)

# Database configuration (match Laravel's .env)
DB_USER = 'root'
DB_PASSWORD = ''
DB_HOST = '127.0.0.1'
DB_NAME = 'health_link'
DB_PORT = 3306

# SQLAlchemy engine for pandas operations
SQLALCHEMY_ENGINE = create_engine(f'mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}/{DB_NAME}')

# MySQL connector config
DB_CONFIG = {
    'host': DB_HOST,
    'database': DB_NAME,
    'user': DB_USER,
    'password': DB_PASSWORD,
    'port': DB_PORT
}

# Configure logging
logging.basicConfig(filename='flask.log', level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger(__name__)

# Directories - Use absolute path and ensure creation
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_FOLDER = os.path.join(BASE_DIR, 'Uploads')

# Maximum distance threshold for search results
MAX_DISTANCE = 350

# Ensure upload directory exists with proper error handling
def ensure_upload_directory():
    try:
        if not os.path.exists(UPLOAD_FOLDER):
            os.makedirs(UPLOAD_FOLDER, exist_ok=True)
            logger.info(f'Created upload directory: {UPLOAD_FOLDER}')
        
        # Test if directory is writable
        test_file = os.path.join(UPLOAD_FOLDER, 'test_write.tmp')
        with open(test_file, 'w') as f:
            f.write('test')
        os.remove(test_file)
        logger.info(f'Upload directory is writable: {UPLOAD_FOLDER}')
        return True
    except Exception as e:
        logger.error(f'Failed to create or access upload directory {UPLOAD_FOLDER}: {str(e)}')
        return False

# Initialize upload directory
if not ensure_upload_directory():
    raise Exception("Cannot create or access upload directory")

# Database connection
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
    
    # Updated query to get complete product data including images
    products_query = """
    SELECT 
        p.product_id,
        p.store_id,
        p.product_name AS name,
        p.product_name,
        p.description,
        COALESCE(p.price, 0) as price,
        p.average_rating,
        p.inventory_price,
        COALESCE(p.stock, 0) as stock,
        COALESCE(p.category, 'Uncategorized') as category,
        COALESCE(p.type, 'unknown') as type,
        p.condition,
        p.added_date,
        p.created_at,
        p.updated_at,
        COALESCE(p.average_rating, 0) AS rating
    FROM products p
    """
    
    products = pd.read_sql(products_query, SQLALCHEMY_ENGINE)
    
    purchases = pd.read_sql("""
        SELECT buyer_id AS user_id, product_id 
        FROM product_orders po 
        JOIN product_order_items poi ON po.product_order_id = poi.product_order_id
    """, SQLALCHEMY_ENGINE)
    
    browsing_history = pd.read_sql("SELECT user_id, product_id FROM browsing_history", SQLALCHEMY_ENGINE)
    
    logger.info("Datasets loaded successfully.")
    
    # Handle any remaining NaN values in the products DataFrame
    products = products.fillna({
        'price': 0,
        'stock': 0,
        'category': 'Uncategorized',
        'type': 'unknown',
        'rating': 0,
        'average_rating': 0
    })
    
    # Validation
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
    
except Exception as e:
    logger.error(f"Error during initialization: {str(e)}")
    raise

def get_product_images(product_ids):
    """Get images for given product IDs"""
    if not product_ids:
        return {}
    
    connection = get_db_connection()
    if connection is None:
        logger.error('Cannot get product images: Database connection failed')
        return {}
    
    cursor = connection.cursor(dictionary=True)
    try:
        # Create placeholders for the IN clause
        placeholders = ','.join(['%s'] * len(product_ids))
        query = f"""
        SELECT 
            id,
            product_id,
            image_path,
            is_primary,
            created_at,
            updated_at
        FROM product_images 
        WHERE product_id IN ({placeholders})
        ORDER BY product_id, is_primary DESC
        """
        
        cursor.execute(query, list(product_ids))
        images_data = cursor.fetchall()
        
        # Group images by product_id
        images_by_product = {}
        for img in images_data:
            product_id = img['product_id']
            if product_id not in images_by_product:
                images_by_product[product_id] = []
            images_by_product[product_id].append({
                'id': img['id'],
                'product_id': img['product_id'],
                'image_path': img['image_path'],
                'is_primary': img['is_primary'],
                'created_at': img['created_at'].isoformat() if img['created_at'] else None,
                'updated_at': img['updated_at'].isoformat() if img['updated_at'] else None
            })
        
        return images_by_product
        
    except Exception as e:
        logger.error(f'Failed to get product images: {str(e)}')
        return {}
    finally:
        cursor.close()
        connection.close()

def clean_nan_values(data):
    """Convert NaN values to None for proper JSON serialization"""
    if isinstance(data, dict):
        return {key: clean_nan_values(value) for key, value in data.items()}
    elif isinstance(data, list):
        return [clean_nan_values(item) for item in data]
    elif isinstance(data, float) and (math.isnan(data) or math.isinf(data)):
        return None
    else:
        return data

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
        
        # Clean NaN values in the score column and sort
        recommended_products['score'] = recommended_products['score'].fillna(0.0)
        recommended_products = recommended_products.sort_values(by='score', ascending=False)

        if recommended_products.empty:
            return jsonify({'error': 'No recommendations available for this user'}), 404

        # Get images for all products
        all_product_ids = list(set(interacted_products['product_id'].tolist() + 
                                 recommended_products['product_id'].tolist()))
        images_by_product = get_product_images(all_product_ids)
        
        # Convert to dict and add images
        interacted_dict = interacted_products.to_dict(orient='records')
        for product in interacted_dict:
            product_id = product['product_id']
            product['images'] = images_by_product.get(product_id, [])
            # Format datetime fields if they exist
            for date_field in ['added_date', 'created_at', 'updated_at']:
                if date_field in product and product[date_field] is not None:
                    if hasattr(product[date_field], 'isoformat'):
                        product[date_field] = product[date_field].isoformat()
        
        recommended_dict = recommended_products.to_dict(orient='records')
        for product in recommended_dict:
            product_id = product['product_id']
            product['images'] = images_by_product.get(product_id, [])
            # Format datetime fields if they exist
            for date_field in ['added_date', 'created_at', 'updated_at']:
                if date_field in product and product[date_field] is not None:
                    if hasattr(product[date_field], 'isoformat'):
                        product[date_field] = product[date_field].isoformat()

        response_data = {
            'interacted_products': clean_nan_values(interacted_dict),
            'recommended_products': clean_nan_values(recommended_dict)
        }

        logger.debug(f"Sending response with complete product data")
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
            
            # Ensure the upload directory still exists before saving
            if not os.path.exists(UPLOAD_FOLDER):
                if not ensure_upload_directory():
                    logger.error('Upload directory unavailable')
                    return jsonify({'error': 'Upload directory unavailable'}), 500
            
            try:
                file.save(filepath)
                logger.info(f'Saved file to: {filepath}')
            except Exception as save_error:
                logger.error(f'Failed to save file {filename}: {str(save_error)}')
                return jsonify({'error': f'Failed to save file {filename}: {str(save_error)}'}), 500

            try:
                try:
                    image_id_int = int(image_id)
                except ValueError:
                    logger.error('Invalid image_id format: %s', image_id)
                    return jsonify({'error': f'Invalid image_id format: {image_id}'}), 400

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
                    try:
                        os.remove(filepath)
                        logger.info('Cleaned up file: %s', filepath)
                    except Exception as cleanup_error:
                        logger.warning('Failed to cleanup file %s: %s', filepath, str(cleanup_error))
    finally:
        cursor.close()
        connection.close()

    if not features_map:
        logger.warning('No features extracted')
        return jsonify({'error': 'No features extracted'}), 400

    logger.info('Feature extraction completed: %s', features_map.keys())
    return jsonify({'features': features_map})

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
                # Validate image_id is numeric
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

# Global variables for FAISS index and image IDs
logger.info('Initializing FAISS index at startup')
index, image_ids = initialize_faiss_index()
logger.info('FAISS initialization complete: index=%s, image_ids=%s', index, image_ids)

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
    
    # Ensure the upload directory exists before saving
    if not os.path.exists(UPLOAD_FOLDER):
        if not ensure_upload_directory():
            logger.error('Upload directory unavailable')
            return jsonify({'error': 'Upload directory unavailable'}), 500
    
    try:
        file.save(filepath)
        logger.info(f'Saved query file to: {filepath}')
    except Exception as save_error:
        logger.error(f'Failed to save query file {filename}: {str(save_error)}')
        return jsonify({'error': f'Failed to save query file: {str(save_error)}'}), 500

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

        k = min(10, index.ntotal)  # Return up to 10 matches
        logger.info('Searching FAISS index for %d nearest neighbors', k)
        distances, indices = index.search(query_vector, k)
        matches = []
        filtered_count = 0
        
        for j, i in enumerate(indices[0]):
            if i >= 0 and i < len(image_ids):
                distance = float(distances[0][j])
                
                # Filter out matches with distance greater than MAX_DISTANCE
                if distance > MAX_DISTANCE:
                    filtered_count += 1
                    logger.info('Filtered out match with distance %.2f (> %.2f)', distance, MAX_DISTANCE)
                    continue
                    
                try:
                    image_id = int(image_ids[i])
                    matches.append({'image_id': image_id, 'distance': distance})
                except ValueError:
                    logger.error('Invalid image_id at index %d: %s', i, image_ids[i])
                    continue
        
        logger.info('Search completed: found %d matches (filtered out %d with distance > %.2f): %s', 
                    len(matches), filtered_count, MAX_DISTANCE, matches)
        return jsonify({'matches': matches, 'filtered_count': filtered_count, 'max_distance': MAX_DISTANCE})
    except Exception as e:
        logger.error('Search failed: %s', str(e))
        return jsonify({'error': str(e)}), 500
    finally:
        if os.path.exists(filepath):
            try:
                os.remove(filepath)
                logger.info('Cleaned up file: %s', filepath)
            except Exception as cleanup_error:
                logger.warning('Failed to cleanup file %s: %s', filepath, str(cleanup_error))

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    logger.info('Starting Flask app on port %d', port)
    try:
        app.run(host='0.0.0.0', port=port, debug=False)
    except Exception as e:
        logger.error('Flask app failed to start: %s', str(e))
        raise