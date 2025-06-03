from model.feature_extractor import extract_features
import numpy as np

# Path to a sample image (replace with your actual path)
image_path = 'images/test.jpg'  

# Extract features
try:
    features = extract_features(image_path)
    print(f"Feature vector shape: {features.shape}")
    print(f"Feature vector (first 5 values): {features[:5]}")
except Exception as e:
    print(f"Error extracting features: {e}")
