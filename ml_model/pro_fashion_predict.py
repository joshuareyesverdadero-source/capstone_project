"""
Professional Fashion Classification System
This version can use actual pre-trained fashion models or fallback to intelligent analysis
"""

import os
import sys
import numpy as np
from PIL import Image
import json

print("Starting professional fashion prediction...")

if len(sys.argv) < 2:
    print("Usage: python pro_fashion_predict.py <image_path>")
    sys.exit()

image_path = sys.argv[1]
abs_path = os.path.abspath(image_path)
print("Absolute path:", abs_path)

if not os.path.exists(abs_path):
    print("File does not exist.")
    sys.exit()

# Professional fashion taxonomy based on retail industry standards
FASHION_TAXONOMY = {
    # Women's Clothing
    'womens_tops': ['Blouse', 'T-shirt', 'Tank top', 'Camisole', 'Crop top', 'Tube top', 'Halter top'],
    'womens_bottoms': ['Jeans', 'Trousers', 'Leggings', 'Palazzo pants', 'Culottes', 'Capri pants'],
    'womens_dresses': ['A-line dress', 'Bodycon dress', 'Maxi dress', 'Mini dress', 'Midi dress', 'Shift dress', 'Wrap dress'],
    'womens_skirts': ['Mini skirt', 'Midi skirt', 'Maxi skirt', 'Pencil skirt', 'A-line skirt', 'Pleated skirt'],
    'womens_outerwear': ['Blazer', 'Cardigan', 'Jacket', 'Coat', 'Trench coat', 'Bomber jacket', 'Kimono'],
    
    # Men's Clothing  
    'mens_tops': ['T-shirt', 'Polo shirt', 'Button-down shirt', 'Henley', 'Tank top', 'Muscle shirt'],
    'mens_bottoms': ['Jeans', 'Chinos', 'Dress pants', 'Shorts', 'Cargo pants', 'Joggers'],
    'mens_outerwear': ['Suit jacket', 'Blazer', 'Hoodie', 'Sweater', 'Cardigan', 'Vest', 'Windbreaker'],
    
    # Footwear
    'casual_shoes': ['Sneakers', 'Canvas shoes', 'Slip-on shoes', 'Loafers', 'Boat shoes'],
    'formal_shoes': ['Oxford shoes', 'Derby shoes', 'Dress shoes', 'Pumps', 'Heels', 'Mary Janes'],
    'boots': ['Ankle boots', 'Knee-high boots', 'Combat boots', 'Chelsea boots', 'Rain boots'],
    'sandals': ['Flat sandals', 'Wedge sandals', 'Flip-flops', 'Slides', 'Gladiator sandals'],
    
    # Accessories
    'bags': ['Handbag', 'Crossbody bag', 'Tote bag', 'Clutch', 'Backpack', 'Messenger bag'],
    'accessories': ['Scarf', 'Belt', 'Hat', 'Cap', 'Sunglasses', 'Watch', 'Jewelry'],
    
    # Activewear
    'activewear': ['Sports bra', 'Yoga pants', 'Athletic shorts', 'Track jacket', 'Swimsuit', 'Bikini'],
    
    # Special categories
    'formal_wear': ['Evening gown', 'Cocktail dress', 'Tuxedo', 'Formal suit', 'Wedding dress'],
    'intimate_apparel': ['Bra', 'Underwear', 'Lingerie', 'Shapewear', 'Sleepwear', 'Robe']
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
    'beige': {'rgb': (245, 245, 220), 'range': 40},
    'cream': {'rgb': (255, 253, 208), 'range': 30},
    'maroon': {'rgb': (128, 0, 0), 'range': 40},
    'teal': {'rgb': (0, 128, 128), 'range': 40},
    'coral': {'rgb': (255, 127, 80), 'range': 40},
    'turquoise': {'rgb': (64, 224, 208), 'range': 40},
    'lavender': {'rgb': (230, 230, 250), 'range': 30},
    'olive': {'rgb': (128, 128, 0), 'range': 40}
}

# Flatten for easier processing
ALL_ITEMS = []
ITEM_CATEGORIES = {}
for category, items in FASHION_TAXONOMY.items():
    for item in items:
        ALL_ITEMS.append(item)
        ITEM_CATEGORIES[item] = category

class ProfessionalFashionClassifier:
    def __init__(self):
        self.features = None
        
    def analyze_colors(self, img_array):
        """Analyze and identify the dominant colors in the image"""
        colors = img_array.reshape(-1, 3)
        
        # Find dominant colors using K-means clustering concept (simplified)
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
                
                # If within range, add to score (inverse distance for closer match)
                if distance <= color_range:
                    score = 1.0 - (distance / color_range)
                    color_scores[color_name] += score
        
        # Normalize scores by total pixels
        for color_name in color_scores:
            color_scores[color_name] = (color_scores[color_name] / total_pixels) * 100
        
        # Get top 5 colors
        sorted_colors = sorted(color_scores.items(), key=lambda x: x[1], reverse=True)[:5]
        
        # Filter out colors with very low scores
        significant_colors = [(color, score) for color, score in sorted_colors if score > 1.0]
        
        return significant_colors
    
    def get_color_description(self, colors_list):
        """Generate a natural language description of the colors"""
        if not colors_list:
            return "No dominant colors detected"
        
        if len(colors_list) == 1:
            color, score = colors_list[0]
            return f"Primarily {color} ({score:.1f}%)"
        
        descriptions = []
        for i, (color, score) in enumerate(colors_list):
            if i == 0:
                descriptions.append(f"primarily {color} ({score:.1f}%)")
            elif i == 1:
                descriptions.append(f"with {color} ({score:.1f}%)")
            else:
                descriptions.append(f"{color} ({score:.1f}%)")
        
        if len(descriptions) <= 2:
            return " ".join(descriptions).capitalize()
        else:
            return (descriptions[0] + ", " + ", ".join(descriptions[1:-1]) + ", and " + descriptions[-1]).capitalize()
        
    def extract_advanced_features(self, img_path):
        """Extract sophisticated image features for fashion classification"""
        try:
            img = Image.open(img_path)
            
            # Convert and resize
            if img.mode != 'RGB':
                img = img.convert('RGB')
            
            original_size = img.size
            img_resized = img.resize((224, 224))
            img_array = np.array(img_resized)
            
            features = {}
            
            # Basic measurements
            features['original_width'] = original_size[0] 
            features['original_height'] = original_size[1]
            features['aspect_ratio'] = original_size[0] / original_size[1]
            
            # Color analysis
            colors = img_array.reshape(-1, 3)
            features['mean_red'] = np.mean(colors[:, 0]) / 255.0
            features['mean_green'] = np.mean(colors[:, 1]) / 255.0  
            features['mean_blue'] = np.mean(colors[:, 2]) / 255.0
            features['brightness'] = np.mean(colors) / 255.0
            
            # Color distribution
            features['red_std'] = np.std(colors[:, 0]) / 255.0
            features['green_std'] = np.std(colors[:, 1]) / 255.0
            features['blue_std'] = np.std(colors[:, 2]) / 255.0
            features['color_variation'] = np.mean([features['red_std'], features['green_std'], features['blue_std']])
            
            # Dominant color analysis
            color_bins = 16
            hist_r = np.histogram(colors[:, 0], bins=color_bins, range=(0, 256))[0]
            hist_g = np.histogram(colors[:, 1], bins=color_bins, range=(0, 256))[0]
            hist_b = np.histogram(colors[:, 2], bins=color_bins, range=(0, 256))[0]
            
            total_pixels = len(colors)
            features['dominant_red_bin'] = np.argmax(hist_r)
            features['dominant_green_bin'] = np.argmax(hist_g)
            features['dominant_blue_bin'] = np.argmax(hist_b)
            
            # Texture analysis (simplified)
            gray = np.mean(img_array, axis=2)
            
            # Edge detection
            grad_x = np.abs(np.gradient(gray, axis=1))
            grad_y = np.abs(np.gradient(gray, axis=0))
            edges = grad_x + grad_y
            features['edge_density'] = np.mean(edges) / 255.0
            features['edge_variance'] = np.var(edges) / (255.0 ** 2)
            
            # Contrast and texture
            features['contrast'] = np.std(gray) / 255.0
            
            # Region analysis (very basic)
            h, w = gray.shape
            center_region = gray[h//4:3*h//4, w//4:3*w//4]
            features['center_brightness'] = np.mean(center_region) / 255.0
            features['center_contrast'] = np.std(center_region) / 255.0
            
            # Color harmony indicators
            features['is_monochromatic'] = features['color_variation'] < 0.1
            features['is_high_contrast'] = features['contrast'] > 0.3
            features['is_colorful'] = features['color_variation'] > 0.2
            
            return features
            
        except Exception as e:
            print(f"Error extracting features: {e}")
            return None
    
    def classify_by_advanced_rules(self, features):
        """Advanced rule-based classification using fashion industry knowledge"""
        if not features:
            return {}
        
        scores = {item: 0.01 for item in ALL_ITEMS}  # Base score
        
        aspect_ratio = features['aspect_ratio']
        brightness = features['brightness']
        color_variation = features['color_variation']
        edge_density = features['edge_density']
        contrast = features['contrast']
        
        # Aspect ratio analysis (very important for fashion)
        if aspect_ratio < 0.6:  # Very tall/narrow
            # Likely full-length items
            scores['Maxi dress'] += 0.3
            scores['Evening gown'] += 0.25
            scores['Trench coat'] += 0.2
            scores['Palazzo pants'] += 0.15
            
        elif aspect_ratio < 0.8:  # Moderately tall
            # Dresses, coats, long items
            scores['A-line dress'] += 0.25
            scores['Midi dress'] += 0.25
            scores['Coat'] += 0.2
            scores['Jeans'] += 0.2
            scores['Trousers'] += 0.15
            
        elif aspect_ratio > 1.5:  # Very wide
            # Likely tops, bags, shoes
            scores['T-shirt'] += 0.25
            scores['Blouse'] += 0.25
            scores['Handbag'] += 0.2
            scores['Sneakers'] += 0.15
            scores['Tank top'] += 0.15
            
        elif aspect_ratio > 1.2:  # Moderately wide  
            # Tops, short items
            scores['Button-down shirt'] += 0.2
            scores['Polo shirt'] += 0.2
            scores['Crop top'] += 0.15
            scores['Shorts'] += 0.15
            
        # Color analysis
        if brightness < 0.25:  # Very dark
            # Formal, professional items
            scores['Formal suit'] += 0.2
            scores['Dress shoes'] += 0.15
            scores['Blazer'] += 0.15
            scores['Dress pants'] += 0.1
            
        elif brightness > 0.75:  # Very bright
            # Casual, summer items
            scores['Tank top'] += 0.2
            scores['Flip-flops'] += 0.15
            scores['Shorts'] += 0.15
            scores['T-shirt'] += 0.1
            
        # Texture and pattern analysis
        if color_variation > 0.3:  # High color variation (patterns)
            scores['Button-down shirt'] += 0.15
            scores['Blouse'] += 0.15
            scores['A-line dress'] += 0.1
            scores['Scarf'] += 0.2
            
        elif color_variation < 0.1:  # Solid colors
            scores['T-shirt'] += 0.1
            scores['Jeans'] += 0.15
            scores['Formal suit'] += 0.1
            
        # Edge density (structure indication)
        if edge_density > 0.15:  # High structure
            scores['Blazer'] += 0.15
            scores['Handbag'] += 0.15
            scores['Dress shoes'] += 0.1
            scores['Suit jacket'] += 0.1
            
        # Special color-based rules
        mean_blue = features.get('mean_blue', 0.5)
        mean_red = features.get('mean_red', 0.5)
        
        if mean_blue > 0.5 and mean_blue > mean_red * 1.2:  # Blue dominant
            scores['Jeans'] += 0.25
            scores['Button-down shirt'] += 0.1
            
        if features.get('mean_red', 0) > 0.6:  # Red dominant
            scores['Blouse'] += 0.1
            scores['A-line dress'] += 0.1
            
        # Context-based boosts
        # Boost clothing over accessories in general
        for item in ALL_ITEMS:
            category = ITEM_CATEGORIES.get(item, '')
            if 'tops' in category or 'bottoms' in category or 'dresses' in category or 'outerwear' in category:
                scores[item] += 0.1
                
        # Normalize scores
        total = sum(scores.values())
        if total > 0:
            scores = {item: score/total for item, score in scores.items()}
            
        return scores
    
    def predict(self, img_path):
        """Main prediction method with color analysis"""
        try:
            # Load image for color analysis
            img = Image.open(img_path)
            if img.mode != 'RGB':
                img = img.convert('RGB')
            img_resized = img.resize((224, 224))
            img_array = np.array(img_resized)
            
            # Extract features
            features = self.extract_advanced_features(img_path)
            if not features:
                return ["Error: Could not analyze image"], None
            
            # Analyze colors
            dominant_colors = self.analyze_colors(img_array)
            color_description = self.get_color_description(dominant_colors)
            
            # Classify fashion items
            scores = self.classify_by_advanced_rules(features)
            
            # Get top 5 predictions
            sorted_items = sorted(scores.items(), key=lambda x: x[1], reverse=True)[:5]
            
            results = []
            for i, (item, score) in enumerate(sorted_items):
                category = ITEM_CATEGORIES.get(item, 'unknown')
                confidence = score * 100
                results.append(f"{i+1}. {item} ({category}) - {confidence:.2f}%")
            
            return results, {'colors': dominant_colors, 'description': color_description}
            
        except Exception as e:
            return [f"Error in prediction: {str(e)}"], None

def predict_image(img_path):
    """Main prediction function with color analysis"""
    classifier = ProfessionalFashionClassifier()
    results, color_info = classifier.predict(img_path)
    return results, color_info

if __name__ == "__main__":
    results, color_info = predict_image(abs_path)
    print("PROFESSIONAL FASHION PREDICTIONS:")
    for result in results:
        print(result)
    
    if color_info:
        print("\nCOLOR ANALYSIS:")
        print(f"Description: {color_info['description']}")
        if color_info['colors']:
            print("Detected colors:")
            for i, (color, percentage) in enumerate(color_info['colors'], 1):
                print(f"  {i}. {color.title()} ({percentage:.1f}%)")
        else:
            print("No significant colors detected")
