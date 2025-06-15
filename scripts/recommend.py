import pandas as pd
from sklearn.metrics.pairwise import cosine_similarity
import sys
import json

# Load dataset
try:
    csv_path = sys.argv[2] if len(sys.argv) > 2 else "./doctor_product_dataset_50x50.csv"
    df = pd.read_csv(csv_path)
except FileNotFoundError:
    print(json.dumps({"error": "CSV file not foundddddddddddd."}))
    sys.exit(1)

# Create user-item matrix
user_item_matrix = df.pivot_table(index='user_id', columns='product', values='interaction', fill_value=0)

# Compute cosine similarity
similarity_matrix = cosine_similarity(user_item_matrix)
similarity_df = pd.DataFrame(similarity_matrix, index=user_item_matrix.index, columns=user_item_matrix.index)

# Recommendation function
def recommend_products_for(user_id, top_n_similar=3):
    if user_id not in similarity_df.index:
        return {"error": f"User {user_id} not found in the dataset."}

    # Get purchased products
    bought = user_item_matrix.loc[user_id]
    purchased = bought[bought > 0].to_dict()

    # Find similar users
    similar_users = similarity_df[user_id].sort_values(ascending=False)[1:top_n_similar+1]

    # Collect recommended products
    recommended = set()
    for other_user in similar_users.index:
        other_bought = user_item_matrix.loc[other_user]
        products_bought = other_bought[other_bought > 0].index.tolist()
        recommended.update(products_bought)

    # Filter out already purchased products
    bought_products = bought[bought > 0].index.tolist()
    final_recommendations = list(recommended - set(bought_products))

    return {
        "user_id": user_id,
        "purchased": purchased,
        "similar_users": similar_users.to_dict(),
        "recommendations": final_recommendations
    }

# Handle CLI input
if __name__ == "__main__":
    # print("args", sys.argv)
    if len(sys.argv) != 3:
        print(json.dumps({"error": "Please provide a user ID."}))
        sys.exit(1)

    try:
        user_id = int(sys.argv[1])  # Expect numeric user_id
        result = recommend_products_for(user_id)
        print(json.dumps(result, indent=2))
    except ValueError:
        print(json.dumps({"error": "User ID must be a number."}))
        sys.exit(1)