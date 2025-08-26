"""
Advanced Color and Fashion Analysis System
Combines fashion classification with detailed color analysis for e-commerce applications
"""

import os
import sys
import numpy as np
from PIL import Image
import json
from collections import defaultdict, Counter

print("Starting advanced color and fashion analysis...")

if len(sys.argv) < 2:
    print("Usage: python color_fashion_predict.py <image_path>")
    sys.exit()

image_path = sys.argv[1]
abs_path = os.path.abspath(image_path)
print("Absolute path:", abs_path)

if not os.path.exists(abs_path):
    print("File does not exist.")
    sys.exit()

# Fashion categories optimized for clothing items
FASHION_CATEGORIES = {
    # Core Clothing
    'tops': ['T-shirt', 'Blouse', 'Tank top', 'Polo shirt', 'Button-down shirt', 'Crop top', 'Camisole', 'Henley'],
    'bottoms': ['Jeans', 'Pants', 'Shorts', 'Leggings', 'Trousers', 'Skirt', 'Palazzo pants', 'Chinos'],
    'dresses': ['Maxi dress', 'Mini dress', 'Midi dress', 'A-line dress', 'Bodycon dress', 'Wrap dress', 'Shift dress'],
    'outerwear': ['Jacket', 'Blazer', 'Coat', 'Cardigan', 'Hoodie', 'Sweater', 'Vest', 'Windbreaker'],
    
    # Footwear
    'footwear': ['Sneakers', 'Boots', 'Sandals', 'Heels', 'Flats', 'Loafers', 'Athletic shoes', 'Dress shoes'],
    
    # Accessories
    'accessories': ['Bag', 'Handbag', 'Backpack', 'Scarf', 'Belt', 'Hat', 'Cap', 'Sunglasses', 'Watch'],
    
    # Special Categories
    'activewear': ['Sports bra', 'Yoga pants', 'Athletic shorts', 'Track jacket', 'Gym wear'],
    'formal_wear': ['Evening gown', 'Cocktail dress', 'Formal suit', 'Tuxedo', 'Business attire'],
    'swimwear': ['Bikini', 'Swimsuit', 'Board shorts', 'Cover-up']
}

# Extended color definitions with fashion-relevant colors
EXTENDED_COLOR_DEFINITIONS = {
    # Basic colors
    'black': {'rgb': (0, 0, 0), 'range': 35, 'category': 'neutral'},
    'white': {'rgb': (255, 255, 255), 'range': 35, 'category': 'neutral'},
    'gray': {'rgb': (128, 128, 128), 'range': 50, 'category': 'neutral'},
    'silver': {'rgb': (192, 192, 192), 'range': 40, 'category': 'neutral'},
    
    # Primary colors
    'red': {'rgb': (255, 0, 0), 'range': 70, 'category': 'primary'},
    'blue': {'rgb': (0, 0, 255), 'range': 70, 'category': 'primary'},
    'yellow': {'rgb': (255, 255, 0), 'range': 70, 'category': 'primary'},
    
    # Secondary colors
    'green': {'rgb': (0, 255, 0), 'range': 70, 'category': 'secondary'},
    'orange': {'rgb': (255, 165, 0), 'range': 60, 'category': 'secondary'},
    'purple': {'rgb': (128, 0, 128), 'range': 70, 'category': 'secondary'},
    
    # Fashion-specific colors
    'navy': {'rgb': (0, 0, 128), 'range': 50, 'category': 'fashion'},
    'beige': {'rgb': (245, 245, 220), 'range': 40, 'category': 'fashion'},
    'cream': {'rgb': (255, 253, 208), 'range': 35, 'category': 'fashion'},
    'ivory': {'rgb': (255, 255, 240), 'range': 30, 'category': 'fashion'},
    'khaki': {'rgb': (240, 230, 140), 'range': 45, 'category': 'fashion'},
    'tan': {'rgb': (210, 180, 140), 'range': 45, 'category': 'fashion'},
    'brown': {'rgb': (139, 69, 19), 'range': 70, 'category': 'fashion'},
    'burgundy': {'rgb': (128, 0, 32), 'range': 50, 'category': 'fashion'},
    'maroon': {'rgb': (128, 0, 0), 'range': 50, 'category': 'fashion'},
    'olive': {'rgb': (128, 128, 0), 'range': 50, 'category': 'fashion'},
    'teal': {'rgb': (0, 128, 128), 'range': 50, 'category': 'fashion'},
    'turquoise': {'rgb': (64, 224, 208), 'range': 50, 'category': 'fashion'},
    'coral': {'rgb': (255, 127, 80), 'range': 50, 'category': 'fashion'},
    'salmon': {'rgb': (250, 128, 114), 'range': 45, 'category': 'fashion'},
    'pink': {'rgb': (255, 192, 203), 'range': 60, 'category': 'fashion'},
    'rose': {'rgb': (255, 0, 127), 'range': 55, 'category': 'fashion'},
    'lavender': {'rgb': (230, 230, 250), 'range': 35, 'category': 'fashion'},
    'mint': {'rgb': (152, 255, 152), 'range': 40, 'category': 'fashion'},
    'emerald': {'rgb': (0, 201, 87), 'range': 45, 'category': 'fashion'},
    'royal_blue': {'rgb': (65, 105, 225), 'range': 45, 'category': 'fashion'},
    'sky_blue': {'rgb': (135, 206, 235), 'range': 50, 'category': 'fashion'},
    'gold': {'rgb': (255, 215, 0), 'range': 55, 'category': 'fashion'},
    'bronze': {'rgb': (205, 127, 50), 'range': 50, 'category': 'fashion'},
    'copper': {'rgb': (184, 115, 51), 'range': 45, 'category': 'fashion'},
    'charcoal': {'rgb': (54, 69, 79), 'range': 40, 'category': 'fashion'}
}

# Color harmony patterns
COLOR_HARMONIES = {
    'monochromatic': 'Single color with variations in shade/tint',
    'complementary': 'Opposite colors on color wheel',
    'analogous': 'Adjacent colors on color wheel',
    'triadic': 'Three evenly spaced colors on color wheel',
    'neutral': 'Blacks, whites, grays, and beiges',
    'warm': 'Reds, oranges, yellows',
    'cool': 'Blues, greens, purples',
    'earth_tones': 'Browns, tans, naturals',
    'pastels': 'Soft, light colors',
    'bold': 'Bright, saturated colors'
}

class AdvancedColorFashionAnalyzer:
    def __init__(self):
        self.color_cache = {}
    
    def extract_detailed_color_info(self, img_array):
        """Extract comprehensive color information from image"""
        colors = img_array.reshape(-1, 3)
        total_pixels = len(colors)
        
        # Color analysis results
        color_analysis = {
            'dominant_colors': [],
            'color_distribution': {},
            'color_harmony': None,
            'brightness_analysis': {},
            'contrast_level': 'medium',
            'saturation_level': 'medium',
            'color_temperature': 'neutral'
        }
        
        # 1. Dominant Color Analysis
        color_scores = defaultdict(float)
        
        for pixel in colors:
            r, g, b = int(pixel[0]), int(pixel[1]), int(pixel[2])
            
            # Calculate distance to each defined color
            for color_name, color_info in EXTENDED_COLOR_DEFINITIONS.items():
                target_r, target_g, target_b = color_info['rgb']
                color_range = color_info['range']
                
                # Calculate Euclidean distance
                distance = np.sqrt((r - target_r)**2 + (g - target_g)**2 + (b - target_b)**2)
                
                # If within range, add to score
                if distance <= color_range:
                    score = 1.0 - (distance / color_range)
                    color_scores[color_name] += score
        
        # Normalize and get top colors
        for color_name in color_scores:
            color_scores[color_name] = (color_scores[color_name] / total_pixels) * 100
        
        # Get significant colors (>2% coverage)
        significant_colors = [(color, score) for color, score in 
                            sorted(color_scores.items(), key=lambda x: x[1], reverse=True) 
                            if score > 2.0][:6]
        
        color_analysis['dominant_colors'] = significant_colors
        
        # 2. Color Distribution by Category
        category_scores = defaultdict(float)
        for color, score in significant_colors:
            category = EXTENDED_COLOR_DEFINITIONS[color]['category']
            category_scores[category] += score
        
        color_analysis['color_distribution'] = dict(category_scores)
        
        # 3. Brightness Analysis
        overall_brightness = np.mean(colors) / 255.0
        brightness_std = np.std(colors) / 255.0
        
        if overall_brightness < 0.25:
            brightness_desc = "Very Dark"
        elif overall_brightness < 0.45:
            brightness_desc = "Dark"
        elif overall_brightness < 0.65:
            brightness_desc = "Medium"
        elif overall_brightness < 0.8:
            brightness_desc = "Bright"
        else:
            brightness_desc = "Very Bright"
        
        color_analysis['brightness_analysis'] = {
            'level': brightness_desc,
            'value': overall_brightness,
            'uniformity': 'High' if brightness_std < 0.1 else 'Medium' if brightness_std < 0.2 else 'Low'
        }
        
        # 4. Contrast Analysis
        contrast = np.std(colors.astype(float), axis=0).mean() / 255.0
        if contrast < 0.15:
            color_analysis['contrast_level'] = 'Low'
        elif contrast < 0.25:
            color_analysis['contrast_level'] = 'Medium'
        else:
            color_analysis['contrast_level'] = 'High'
        
        # 5. Saturation Analysis
        hsv_colors = []
        for pixel in colors[:1000]:  # Sample for performance
            r, g, b = pixel[0]/255.0, pixel[1]/255.0, pixel[2]/255.0
            max_val = max(r, g, b)
            min_val = min(r, g, b)
            saturation = (max_val - min_val) / max_val if max_val > 0 else 0
            hsv_colors.append(saturation)
        
        avg_saturation = np.mean(hsv_colors)
        if avg_saturation < 0.2:
            color_analysis['saturation_level'] = 'Low (Muted)'
        elif avg_saturation < 0.5:
            color_analysis['saturation_level'] = 'Medium'
        else:
            color_analysis['saturation_level'] = 'High (Vibrant)'
        
        # 6. Color Temperature
        red_avg = np.mean(colors[:, 0])
        blue_avg = np.mean(colors[:, 2])
        
        if red_avg > blue_avg * 1.15:
            color_analysis['color_temperature'] = 'Warm'
        elif blue_avg > red_avg * 1.15:
            color_analysis['color_temperature'] = 'Cool'
        else:
            color_analysis['color_temperature'] = 'Neutral'
        
        # 7. Color Harmony Detection
        color_analysis['color_harmony'] = self.detect_color_harmony(significant_colors, category_scores)
        
        return color_analysis
    
    def detect_color_harmony(self, colors, category_scores):
        """Detect the type of color harmony in the image"""
        if not colors:
            return 'Unknown'
        
        # Check for neutral dominance
        if category_scores.get('neutral', 0) > 60:
            return 'Neutral palette'
        
        # Check for single color dominance (monochromatic)
        if len([c for c in colors if c[1] > 15]) <= 2:
            return 'Monochromatic'
        
        # Check for warm/cool dominance
        warm_colors = ['red', 'orange', 'yellow', 'coral', 'salmon', 'pink', 'gold']
        cool_colors = ['blue', 'green', 'purple', 'teal', 'turquoise', 'navy', 'royal_blue', 'sky_blue']
        
        warm_score = sum(score for color, score in colors if color in warm_colors)
        cool_score = sum(score for color, score in colors if color in cool_colors)
        
        if warm_score > cool_score * 2:
            return 'Warm color scheme'
        elif cool_score > warm_score * 2:
            return 'Cool color scheme'
        
        # Check for earth tones
        earth_colors = ['brown', 'tan', 'beige', 'khaki', 'olive', 'bronze', 'copper']
        earth_score = sum(score for color, score in colors if color in earth_colors)
        
        if earth_score > 30:
            return 'Earth tone palette'
        
        # Check for pastels
        pastel_colors = ['lavender', 'mint', 'cream', 'ivory', 'sky_blue']
        pastel_score = sum(score for color, score in colors if color in pastel_colors)
        
        if pastel_score > 25:
            return 'Pastel color scheme'
        
        return 'Mixed color palette'
    
    def extract_fashion_features(self, img_path):
        """Extract features relevant to fashion classification"""
        try:
            img = Image.open(img_path)
            if img.mode != 'RGB':
                img = img.convert('RGB')
            
            # Resize for consistent analysis
            img = img.resize((224, 224))
            img_array = np.array(img)
            
            features = {}
            
            # Basic properties
            features['aspect_ratio'] = img.size[0] / img.size[1]
            
            # Color properties
            avg_colors = np.mean(img_array, axis=(0, 1))
            features['brightness'] = np.mean(avg_colors) / 255.0
            features['color_variance'] = np.var(img_array) / (255.0 ** 2)
            
            # Edge detection (texture analysis)
            gray = np.mean(img_array, axis=2)
            grad_x = np.abs(np.gradient(gray, axis=1))
            grad_y = np.abs(np.gradient(gray, axis=0))
            edges = grad_x + grad_y
            features['edge_density'] = np.mean(edges) / 255.0
            features['texture_complexity'] = np.var(edges) / (255.0 ** 2)
            
            # Color distribution
            features['red_dominance'] = avg_colors[0] / 255.0
            features['green_dominance'] = avg_colors[1] / 255.0
            features['blue_dominance'] = avg_colors[2] / 255.0
            
            return features
            
        except Exception as e:
            print(f"Error extracting features: {e}")
            return None
    
    def classify_fashion_item(self, features, color_info):
        """Classify fashion item using features and color information"""
        if not features:
            return {}
        
        # Initialize scores for all items
        all_items = []
        for category_items in FASHION_CATEGORIES.values():
            all_items.extend(category_items)
        
        scores = {item: 0.05 for item in all_items}  # Base score
        
        # Feature-based classification
        aspect_ratio = features['aspect_ratio']
        brightness = features['brightness']
        edge_density = features['edge_density']
        color_variance = features['color_variance']
        
        # Aspect ratio rules
        if aspect_ratio < 0.7:  # Tall items
            for item in FASHION_CATEGORIES['dresses']:
                scores[item] += 0.25
            for item in FASHION_CATEGORIES['bottoms']:
                scores[item] += 0.2
            for item in ['Maxi dress', 'Coat', 'Long jacket']:
                if item in scores:
                    scores[item] += 0.15
                    
        elif aspect_ratio > 1.3:  # Wide items
            for item in FASHION_CATEGORIES['tops']:
                scores[item] += 0.2
            for item in FASHION_CATEGORIES['accessories']:
                scores[item] += 0.15
        
        # Brightness rules
        if brightness < 0.3:  # Dark items
            for item in FASHION_CATEGORIES['formal_wear']:
                scores[item] += 0.2
            for item in ['Blazer', 'Formal suit', 'Evening gown']:
                if item in scores:
                    scores[item] += 0.15
                    
        elif brightness > 0.7:  # Bright items
            for item in FASHION_CATEGORIES['activewear']:
                scores[item] += 0.2
            for item in ['T-shirt', 'Tank top', 'Summer dress']:
                if item in scores:
                    scores[item] += 0.15
        
        # Color-based rules
        dominant_colors = color_info.get('dominant_colors', [])
        if dominant_colors:
            primary_color = dominant_colors[0][0]
            
            # Denim detection
            if primary_color in ['blue', 'navy']:
                for item in ['Jeans', 'Denim jacket']:
                    if item in scores:
                        scores[item] += 0.3
            
            # Formal color detection
            if primary_color in ['black', 'navy', 'charcoal']:
                for item in FASHION_CATEGORIES['formal_wear']:
                    scores[item] += 0.15
            
            # Casual bright colors
            if primary_color in ['red', 'yellow', 'orange', 'pink']:
                for item in ['T-shirt', 'Tank top', 'Casual dress']:
                    if item in scores:
                        scores[item] += 0.1
        
        # Texture/structure rules
        if edge_density > 0.15:  # High structure
            for item in ['Blazer', 'Structured jacket', 'Bag']:
                if item in scores:
                    scores[item] += 0.15
        
        # Normalize scores
        total = sum(scores.values())
        if total > 0:
            scores = {item: score/total for item, score in scores.items()}
        
        return scores
    
    def predict(self, img_path):
        """Main prediction function combining fashion and color analysis"""
        try:
            # Load and process image
            img = Image.open(img_path)
            if img.mode != 'RGB':
                img = img.convert('RGB')
            img_resized = img.resize((224, 224))
            img_array = np.array(img_resized)
            
            # Extract fashion features
            features = self.extract_fashion_features(img_path)
            if not features:
                return ["Error: Could not extract features"], None
            
            # Detailed color analysis
            color_analysis = self.extract_detailed_color_info(img_array)
            
            # Fashion classification
            fashion_scores = self.classify_fashion_item(features, color_analysis)
            
            # Get top 5 fashion predictions
            sorted_fashion = sorted(fashion_scores.items(), key=lambda x: x[1], reverse=True)[:5]
            
            fashion_results = []
            for i, (item, score) in enumerate(sorted_fashion):
                # Find category for this item
                category = 'unknown'
                for cat_name, items in FASHION_CATEGORIES.items():
                    if item in items:
                        category = cat_name
                        break
                
                confidence = score * 100
                fashion_results.append(f"{i+1}. {item} ({category}) - {confidence:.2f}%")
            
            return fashion_results, color_analysis
            
        except Exception as e:
            return [f"Error in prediction: {str(e)}"], None

def format_color_analysis_output(color_analysis):
    """Format color analysis for display"""
    if not color_analysis:
        return []
    
    output = []
    
    # Dominant colors
    if color_analysis.get('dominant_colors'):
        output.append("DOMINANT COLORS:")
        for i, (color, percentage) in enumerate(color_analysis['dominant_colors'], 1):
            output.append(f"{i}. {color.replace('_', ' ').title()} ({percentage:.1f}%)")
    
    # Color categories
    if color_analysis.get('color_distribution'):
        output.append("\nCOLOR CATEGORIES:")
        for category, percentage in sorted(color_analysis['color_distribution'].items(), 
                                         key=lambda x: x[1], reverse=True):
            output.append(f"• {category.title()}: {percentage:.1f}%")
    
    # Color characteristics
    output.append("\nCOLOR CHARACTERISTICS:")
    brightness = color_analysis.get('brightness_analysis', {})
    output.append(f"• Brightness: {brightness.get('level', 'Unknown')}")
    output.append(f"• Contrast: {color_analysis.get('contrast_level', 'Unknown')}")
    output.append(f"• Saturation: {color_analysis.get('saturation_level', 'Unknown')}")
    output.append(f"• Color Temperature: {color_analysis.get('color_temperature', 'Unknown')}")
    output.append(f"• Color Harmony: {color_analysis.get('color_harmony', 'Unknown')}")
    
    return output

def predict_image(img_path):
    """Main prediction function"""
    analyzer = AdvancedColorFashionAnalyzer()
    fashion_results, color_analysis = analyzer.predict(img_path)
    return fashion_results, color_analysis

if __name__ == "__main__":
    fashion_results, color_analysis = predict_image(abs_path)
    
    print("ADVANCED FASHION PREDICTIONS:")
    for result in fashion_results:
        print(result)
    
    if color_analysis:
        print("\nDETAILED COLOR ANALYSIS:")
        color_output = format_color_analysis_output(color_analysis)
        for line in color_output:
            print(line)
