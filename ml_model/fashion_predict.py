import os
import sys
import numpy as np
from PIL import Image
import requests
import json

print("Starting fashion prediction...")

if len(sys.argv) < 2:
    print("Usage: python fashion_predict.py <image_path>")
    sys.exit()

image_path = sys.argv[1]
abs_path = os.path.abspath(image_path)
print("Absolute path:", abs_path)

if not os.path.exists(abs_path):
    print("File does not exist.")
    sys.exit()

# Fashion categories specifically for clothing items
FASHION_CATEGORIES = {
    0: 'T-shirt/top',
    1: 'Trouser/Pants', 
    2: 'Pullover/Sweater',
    3: 'Dress',
    4: 'Coat/Jacket',
    5: 'Sandal',
    6: 'Shirt/Blouse',
    7: 'Sneaker/Shoe',
    8: 'Bag/Handbag',
    9: 'Ankle boot/Boot',
    10: 'Skirt',
    11: 'Shorts',
    12: 'Cardigan',
    13: 'Hoodie/Sweatshirt',
    14: 'Tank top/Camisole',
    15: 'Jeans',
    16: 'Blazer',
    17: 'Scarf',
    18: 'Hat/Cap',
    19: 'Swimwear'
}

# Basic color definitions
COLOR_DEFINITIONS = {
    'black': {'rgb': (0, 0, 0), 'range': 30},
    'white': {'rgb': (255, 255, 255), 'range': 30},
    'red': {'rgb': (255, 0, 0), 'range': 60},
    'blue': {'rgb': (0, 0, 255), 'range': 60},
    'green': {'rgb': (0, 255, 0), 'range': 60},
    'yellow': {'rgb': (255, 255, 0), 'range': 60},
    'orange': {'rgb': (255, 165, 0), 'range': 50},
    'purple': {'rgb': (128, 0, 128), 'range': 60},
    'pink': {'rgb': (255, 192, 203), 'range': 50},
    'brown': {'rgb': (139, 69, 19), 'range': 60},
    'gray': {'rgb': (128, 128, 128), 'range': 50}
}

def analyze_image_features(img_path):
    """
    Analyze image features for fashion classification
    This is a simplified approach that looks at image characteristics
    """
    try:
        img = Image.open(img_path)
        
        # Convert to RGB if needed
        if img.mode != 'RGB':
            img = img.convert('RGB')
        
        # Get image dimensions and basic stats
        width, height = img.size
        aspect_ratio = width / height
        
        # Convert to numpy array for analysis
        img_array = np.array(img)
        
        # Analyze color distribution
        avg_colors = np.mean(img_array, axis=(0, 1))
        color_variance = np.var(img_array, axis=(0, 1))
        
        # Simple heuristics based on image characteristics
        features = {
            'aspect_ratio': aspect_ratio,
            'avg_red': avg_colors[0] / 255.0,
            'avg_green': avg_colors[1] / 255.0,
            'avg_blue': avg_colors[2] / 255.0,
            'color_variance': np.mean(color_variance) / 255.0,
            'brightness': np.mean(avg_colors) / 255.0
        }
        
        return features
        
    except Exception as e:
        print(f"Error analyzing image: {e}")
        return None

def classify_fashion_item(features):
    """
    Classify fashion item based on analyzed features
    This uses simple heuristics - in production you'd use a trained model
    """
    if features is None:
        return {}
    
    # Initialize probabilities
    probabilities = {}
    
    # Simple classification logic based on image features
    # This is a placeholder for actual ML model predictions
    
    # Default base probabilities
    for cat_id, cat_name in FASHION_CATEGORIES.items():
        probabilities[cat_id] = 0.05  # Base probability
    
    # Adjust probabilities based on features
    brightness = features['brightness']
    aspect_ratio = features['aspect_ratio']
    color_variance = features['color_variance']
    
    # Clothing items are more likely than accessories
    clothing_items = [0, 1, 2, 3, 4, 6, 10, 11, 12, 13, 14, 15, 16]
    for item in clothing_items:
        probabilities[item] += 0.15
    
    # Tall/narrow images more likely to be dresses, coats, pants
    if aspect_ratio < 0.8:  # Tall image
        probabilities[3] += 0.2  # Dress
        probabilities[4] += 0.15  # Coat
        probabilities[1] += 0.1   # Pants
    
    # Wide images more likely to be tops, sweaters
    elif aspect_ratio > 1.2:  # Wide image
        probabilities[0] += 0.2  # T-shirt
        probabilities[2] += 0.15  # Pullover
        probabilities[6] += 0.1   # Shirt
    
    # Dark images might be formal wear
    if brightness < 0.3:
        probabilities[4] += 0.1   # Coat
        probabilities[16] += 0.1  # Blazer
        probabilities[1] += 0.05  # Pants
    
    # Bright images might be casual wear
    elif brightness > 0.7:
        probabilities[0] += 0.1   # T-shirt
        probabilities[14] += 0.1  # Tank top
        probabilities[11] += 0.05 # Shorts
    
    # High color variance might indicate patterns (shirts, dresses)
    if color_variance > 0.1:
        probabilities[0] += 0.1   # T-shirt
        probabilities[3] += 0.1   # Dress
        probabilities[6] += 0.1   # Shirt
    
    # Normalize probabilities
    total = sum(probabilities.values())
    for cat_id in probabilities:
        probabilities[cat_id] = probabilities[cat_id] / total
    
    return probabilities

def analyze_colors(img_array):
    """Analyze and identify the dominant colors in the image"""
    colors = img_array.reshape(-1, 3)
    
    from collections import defaultdict
    color_scores = defaultdict(float)
    total_pixels = len(colors)
    
    for pixel in colors:
        r, g, b = int(pixel[0]), int(pixel[1]), int(pixel[2])
        
        # Calculate distance to each defined color
        for color_name, color_info in COLOR_DEFINITIONS.items():
            target_r, target_g, target_b = color_info['rgb']
            color_range = color_info['range']
            
            # Calculate Euclidean distance
            distance = np.sqrt((r - target_r)**2 + (g - target_g)**2 + (b - target_b)**2)
            
            # If within range, add to score
            if distance <= color_range:
                score = 1.0 - (distance / color_range)
                color_scores[color_name] += score
    
    # Normalize scores
    for color_name in color_scores:
        color_scores[color_name] = (color_scores[color_name] / total_pixels) * 100
    
    # Get top 3 colors
    sorted_colors = sorted(color_scores.items(), key=lambda x: x[1], reverse=True)[:3]
    significant_colors = [(color, score) for color, score in sorted_colors if score > 1.0]
    
    return significant_colors

def predict_image(img_path):
    """Main prediction function with color analysis"""
    try:
        # Analyze image features
        features = analyze_image_features(img_path)
        if features is None:
            return ["Error: Could not analyze image"], None
        
        # Load image for color analysis
        img = Image.open(img_path)
        if img.mode != 'RGB':
            img = img.convert('RGB')
        img_resized = img.resize((224, 224))
        img_array = np.array(img_resized)
        
        # Analyze colors
        dominant_colors = analyze_colors(img_array)
        
        # Classify fashion item
        probabilities = classify_fashion_item(features)
        
        # Get top 5 predictions
        sorted_predictions = sorted(probabilities.items(), key=lambda x: x[1], reverse=True)[:5]
        
        results = []
        for i, (cat_id, prob) in enumerate(sorted_predictions):
            category = FASHION_CATEGORIES[cat_id]
            confidence = prob * 100
            results.append(f"{i+1}. {category} ({confidence:.2f}%)")
        
        return results, dominant_colors
        
    except Exception as e:
        return [f"Error in prediction: {str(e)}"], None

if __name__ == "__main__":
    results, colors = predict_image(abs_path)
    print("FASHION PREDICTIONS:")
    for result in results:
        print(result)
    
    if colors:
        print("\nCOLOR ANALYSIS:")
        for i, (color, percentage) in enumerate(colors, 1):
            print(f"{i}. {color.title()} ({percentage:.1f}%)")
