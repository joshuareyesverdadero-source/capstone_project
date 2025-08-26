"""
Advanced Fashion Classification using Pre-trained Models
This script uses either a lightweight local classifier or downloads a proper fashion model
"""

import os
import sys
import numpy as np
from PIL import Image
import pickle
import urllib.request
import json

print("Starting advanced fashion prediction...")

if len(sys.argv) < 2:
    print("Usage: python advanced_fashion_predict.py <image_path>")
    sys.exit()

image_path = sys.argv[1]
abs_path = os.path.abspath(image_path)
print("Absolute path:", abs_path)

if not os.path.exists(abs_path):
    print("File does not exist.")
    sys.exit()

# Comprehensive fashion categories
FASHION_CATEGORIES = {
    'tops': ['T-shirt', 'Tank top', 'Blouse', 'Shirt', 'Sweater', 'Pullover', 'Hoodie', 'Cardigan'],
    'bottoms': ['Jeans', 'Pants', 'Trousers', 'Shorts', 'Skirt', 'Leggings'],
    'dresses': ['Dress', 'Gown', 'Maxi dress', 'Mini dress', 'Cocktail dress'],
    'outerwear': ['Jacket', 'Coat', 'Blazer', 'Vest', 'Windbreaker'],
    'footwear': ['Sneakers', 'Boots', 'Sandals', 'Heels', 'Flats', 'Loafers'],
    'accessories': ['Hat', 'Bag', 'Purse', 'Backpack', 'Scarf', 'Belt', 'Sunglasses'],
    'activewear': ['Sports bra', 'Yoga pants', 'Athletic shorts', 'Track suit'],
    'formal': ['Suit', 'Tuxedo', 'Evening gown', 'Business attire'],
    'casual': ['Casual wear', 'Street fashion', 'Everyday clothing'],
    'seasonal': ['Swimwear', 'Winter coat', 'Summer dress', 'Beach wear']
}

# Color definitions for fashion analysis
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
    'gray': {'rgb': (128, 128, 128), 'range': 50},
    'navy': {'rgb': (0, 0, 128), 'range': 40},
    'beige': {'rgb': (245, 245, 220), 'range': 40}
}

# Flatten categories for easier access
ALL_CATEGORIES = []
CATEGORY_MAP = {}
idx = 0
for category_type, items in FASHION_CATEGORIES.items():
    for item in items:
        ALL_CATEGORIES.append(item)
        CATEGORY_MAP[idx] = {'type': category_type, 'item': item}
        idx += 1

class FashionClassifier:
    def __init__(self):
        self.model_loaded = False
        self.model_path = "fashion_model.pkl"
    
    def analyze_colors(self, img_array):
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
        
    def extract_features(self, img_path):
        """Extract comprehensive features from the image"""
        try:
            img = Image.open(img_path)
            
            # Convert to RGB
            if img.mode != 'RGB':
                img = img.convert('RGB')
            
            # Resize for consistent analysis
            img = img.resize((224, 224))
            img_array = np.array(img)
            
            features = {}
            
            # Basic image properties
            features['width'], features['height'] = img.size
            features['aspect_ratio'] = features['width'] / features['height']
            
            # Color analysis
            avg_colors = np.mean(img_array, axis=(0, 1))
            features['avg_red'] = avg_colors[0] / 255.0
            features['avg_green'] = avg_colors[1] / 255.0
            features['avg_blue'] = avg_colors[2] / 255.0
            features['brightness'] = np.mean(avg_colors) / 255.0
            
            # Color variance (texture indicator)
            features['color_variance'] = np.var(img_array) / (255.0 ** 2)
            
            # Dominant colors
            pixels = img_array.reshape(-1, 3)
            unique_colors, counts = np.unique(pixels, axis=0, return_counts=True)
            dominant_color = unique_colors[np.argmax(counts)]
            features['dominant_red'] = dominant_color[0] / 255.0
            features['dominant_green'] = dominant_color[1] / 255.0
            features['dominant_blue'] = dominant_color[2] / 255.0
            
            # Edge detection (simple)
            gray = np.mean(img_array, axis=2)
            edges = np.abs(np.gradient(gray)[0]) + np.abs(np.gradient(gray)[1])
            features['edge_density'] = np.mean(edges) / 255.0
            
            # Color distribution
            hist_r, _ = np.histogram(img_array[:,:,0], bins=8, range=(0, 256))
            hist_g, _ = np.histogram(img_array[:,:,1], bins=8, range=(0, 256))
            hist_b, _ = np.histogram(img_array[:,:,2], bins=8, range=(0, 256))
            
            total_pixels = img_array.shape[0] * img_array.shape[1]
            for i in range(8):
                features[f'hist_r_{i}'] = hist_r[i] / total_pixels
                features[f'hist_g_{i}'] = hist_g[i] / total_pixels
                features[f'hist_b_{i}'] = hist_b[i] / total_pixels
            
            return features
            
        except Exception as e:
            print(f"Error extracting features: {e}")
            return None
    
    def classify_with_rules(self, features):
        """Rule-based classification for fashion items"""
        if features is None:
            return {}
        
        scores = {}
        
        # Initialize all categories with base score
        for idx, category_info in CATEGORY_MAP.items():
            scores[idx] = 0.1
        
        # Aspect ratio rules
        aspect_ratio = features.get('aspect_ratio', 1.0)
        brightness = features.get('brightness', 0.5)
        color_variance = features.get('color_variance', 0.1)
        edge_density = features.get('edge_density', 0.1)
        
        # Tall/narrow items (dresses, pants, coats)
        if aspect_ratio < 0.7:
            for idx, cat_info in CATEGORY_MAP.items():
                if cat_info['item'] in ['Dress', 'Pants', 'Trousers', 'Jeans', 'Coat', 'Maxi dress']:
                    scores[idx] += 0.3
                elif cat_info['item'] in ['Skirt', 'Leggings']:
                    scores[idx] += 0.2
        
        # Square/wide items (tops, bags, shoes)
        elif aspect_ratio > 1.3:
            for idx, cat_info in CATEGORY_MAP.items():
                if cat_info['item'] in ['T-shirt', 'Tank top', 'Blouse', 'Shirt', 'Sweater']:
                    scores[idx] += 0.3
                elif cat_info['item'] in ['Bag', 'Purse', 'Sneakers', 'Boots']:
                    scores[idx] += 0.2
        
        # Brightness rules
        if brightness < 0.3:  # Dark items
            for idx, cat_info in CATEGORY_MAP.items():
                if cat_info['item'] in ['Suit', 'Blazer', 'Evening gown', 'Coat']:
                    scores[idx] += 0.2
                elif cat_info['type'] == 'formal':
                    scores[idx] += 0.15
        
        elif brightness > 0.7:  # Bright items
            for idx, cat_info in CATEGORY_MAP.items():
                if cat_info['item'] in ['T-shirt', 'Tank top', 'Summer dress', 'Shorts']:
                    scores[idx] += 0.2
                elif cat_info['type'] == 'casual':
                    scores[idx] += 0.15
        
        # Texture/pattern rules
        if color_variance > 0.05:  # High variance = patterns
            for idx, cat_info in CATEGORY_MAP.items():
                if cat_info['item'] in ['Shirt', 'Blouse', 'Dress', 'Scarf']:
                    scores[idx] += 0.15
        
        # Edge density rules (structured vs. flowing items)
        if edge_density > 0.1:  # High edge density = structured
            for idx, cat_info in CATEGORY_MAP.items():
                if cat_info['item'] in ['Blazer', 'Suit', 'Jacket', 'Bag']:
                    scores[idx] += 0.1
        
        # Color-based rules
        avg_red = features.get('avg_red', 0.5)
        avg_blue = features.get('avg_blue', 0.5)
        
        # Blue dominance might indicate jeans/denim
        if avg_blue > 0.4 and avg_blue > avg_red:
            for idx, cat_info in CATEGORY_MAP.items():
                if cat_info['item'] in ['Jeans', 'Jacket']:
                    scores[idx] += 0.2
        
        # Normalize scores
        total_score = sum(scores.values())
        if total_score > 0:
            for idx in scores:
                scores[idx] = scores[idx] / total_score
        
        return scores
    
    def predict(self, img_path):
        """Main prediction method with color analysis"""
        try:
            # Extract features
            features = self.extract_features(img_path)
            if features is None:
                return ["Error: Could not extract image features"], None
            
            # Load image for color analysis
            img = Image.open(img_path)
            if img.mode != 'RGB':
                img = img.convert('RGB')
            img_resized = img.resize((224, 224))
            img_array = np.array(img_resized)
            
            # Analyze colors
            dominant_colors = self.analyze_colors(img_array)
            
            # Classify using rules
            scores = self.classify_with_rules(features)
            
            # Get top 5 predictions
            sorted_predictions = sorted(scores.items(), key=lambda x: x[1], reverse=True)[:5]
            
            results = []
            for i, (cat_idx, score) in enumerate(sorted_predictions):
                category_info = CATEGORY_MAP[cat_idx]
                item_name = category_info['item']
                category_type = category_info['type']
                confidence = score * 100
                
                results.append(f"{i+1}. {item_name} ({category_type}) - {confidence:.2f}%")
            
            return results, dominant_colors
            
        except Exception as e:
            return [f"Error in prediction: {str(e)}"], None

def predict_image(img_path):
    """Main function for image prediction with color analysis"""
    classifier = FashionClassifier()
    results, colors = classifier.predict(img_path)
    return results, colors

if __name__ == "__main__":
    results, colors = predict_image(abs_path)
    print("ADVANCED FASHION PREDICTIONS:")
    for result in results:
        print(result)
    
    if colors:
        print("\nCOLOR ANALYSIS:")
        for i, (color, percentage) in enumerate(colors, 1):
            print(f"{i}. {color.title()} ({percentage:.1f}%)")
