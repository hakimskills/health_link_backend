import pandas as pd
import logging
import os
from flask import Flask, request, jsonify
from sqlalchemy import create_engine
from model import hybrid_recommendation

app = Flask(__name__)
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Database connection
DB_USER = 'root'
DB_PASSWORD = ''
DB_HOST = '127.0.0.1'
DB_NAME = 'recommand'
engine = create_engine(f'mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}/{DB_NAME}')

try:
    logger.info("Loading datasets from database...")
    users = pd.read_sql("SELECT id AS user_id, first_name, last_name, email, role FROM users", engine)
    products = pd.read_sql("SELECT product_id, product_name AS name, description, category, COALESCE(average_rating, 0) AS rating, price FROM products", engine)
    purchases = pd.read_sql("SELECT buyer_id AS user_id, product_id FROM product_orders po JOIN product_order_items poi ON po.product_order_id = poi.product_order_id", engine)
    browsing_history = pd.read_sql("SELECT user_id, product_id FROM browsing_history", engine)
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

if __name__ == '__main__':
    port = int(os.environ.get("PORT", 5000))
    app.run(host='0.0.0.0', port=port, debug=False)