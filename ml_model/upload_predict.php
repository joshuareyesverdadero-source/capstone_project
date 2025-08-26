<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $targetDir = "test_images/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $filename = basename($_FILES["image"]["name"]);
    $targetFile = $targetDir . $filename;

    if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
        // Change to the correct directory
        $originalDir = getcwd();
        chdir(__DIR__);
        
        // Try different Python commands with fashion prediction scripts
        $pythonCommands = [
            "C:\\Users\\newpc\\AppData\\Local\\Programs\\Python\\Python310\\python.exe",
            "C:\\Windows\\py.exe",
            "py", 
            "python", 
            "python3"
        ];
        
        // Try different prediction scripts in order of preference
        $predictionScripts = [
            "color_fashion_predict.py",     // New advanced color + fashion model
            "pro_fashion_predict.py",
            "advanced_fashion_predict.py",
            "fashion_predict.py", 
            "predict.py"                     // Original (not recommended for fashion)
        ];
        
        $cmd = null;
        $output = null;
        $successful = false;
        
        foreach ($predictionScripts as $script) {
            if ($successful) break;
            
            foreach ($pythonCommands as $pythonCmd) {
                $cmd = "$pythonCmd $script \"$targetFile\" 2>&1";
                $output = shell_exec($cmd);
                
                // Check for successful execution - look for actual content, not errors
                if (!empty(trim($output)) && 
                    !str_contains(strtolower($output), "can't find") && 
                    !str_contains(strtolower($output), "not recognized") &&
                    !str_contains(strtolower($output), "command not found") &&
                    !str_contains(strtolower($output), "default python") &&
                    !str_contains(strtolower($output), "is not recognized") &&
                    (str_contains(strtolower($output), "starting") || 
                     str_contains($output, "PREDICTIONS:") ||
                     str_contains($output, "FASHION PREDICTIONS:") ||
                     preg_match('/\([\d.]+%\)/', $output))) {
                    $successful = true;
                    break;
                }
            }
        }
        
        // Change back to original directory
        chdir($originalDir);

        echo "<h3>Image uploaded successfully!</h3>";
        echo "<p><strong>Executed command:</strong> $cmd</p>";
        echo "<p><strong>Raw Output:</strong><br><pre>$output</pre></p>";
        
        // Extract all prediction results
        $lines = explode("\n", $output);
        $predictions = [];
        $colors = [];
        $colorCharacteristics = [];
        $colorCategories = [];
        $inPredictions = false;
        $inColors = false;
        $inCharacteristics = false;
        $inCategories = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check for different prediction headers
            if ($line === "PREDICTIONS:" || $line === "FASHION PREDICTIONS:" || $line === "ADVANCED FASHION PREDICTIONS:" || $line === "PROFESSIONAL FASHION PREDICTIONS:") {
                $inPredictions = true;
                $inColors = false;
                $inCharacteristics = false;
                $inCategories = false;
                continue;
            }
            
            // Check for color analysis headers
            if ($line === "COLOR ANALYSIS:" || $line === "DETAILED COLOR ANALYSIS:" || strpos($line, "DOMINANT COLORS:") === 0) {
                $inColors = true;
                $inPredictions = false;
                $inCharacteristics = false;
                $inCategories = false;
                continue;
            }
            
            // Check for color characteristics
            if ($line === "COLOR CHARACTERISTICS:") {
                $inCharacteristics = true;
                $inColors = false;
                $inPredictions = false;
                $inCategories = false;
                continue;
            }
            
            // Check for color categories
            if ($line === "COLOR CATEGORIES:") {
                $inCategories = true;
                $inColors = false;
                $inPredictions = false;
                $inCharacteristics = false;
                continue;
            }
            
            // Parse prediction lines
            if ($inPredictions && preg_match('/^\d+\.\s+(.+?)\s*[-–]?\s*\(([\d.]+)%\)$/', $line, $matches)) {
                $predictions[] = [
                    'label' => trim($matches[1]),
                    'confidence' => $matches[2],
                    'full' => $line
                ];
            }
            
            // Parse dominant color lines
            if ($inColors && preg_match('/^\d+\.\s+(.+?)\s*\(([\d.]+)%\)$/', $line, $matches)) {
                $colors[] = [
                    'color' => trim($matches[1]),
                    'percentage' => $matches[2]
                ];
            }
            
            // Parse color characteristics
            if ($inCharacteristics && preg_match('/^•\s+(.+?):\s*(.+)$/', $line, $matches)) {
                $colorCharacteristics[trim($matches[1])] = trim($matches[2]);
            }
            
            // Parse color categories
            if ($inCategories && preg_match('/^•\s+(.+?):\s*([\d.]+)%$/', $line, $matches)) {
                $colorCategories[] = [
                    'category' => trim($matches[1]),
                    'percentage' => $matches[2]
                ];
            }
            
            // Parse color description line
            if ($inColors && strpos($line, 'Description:') === 0) {
                $colorDescription = trim(substr($line, 12));
            }
        }
        
        if (!empty($predictions)) {
            echo "<div style='background: #e8f5e8; padding: 15px; border: 1px solid #4caf50; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4 style='margin-top: 0; color: #2e7d32;'>Fashion AI Predictions for Product Categorization:</h4>";
            echo "<ol style='margin: 0; padding-left: 20px;'>";
            foreach ($predictions as $pred) {
                $confidence = floatval($pred['confidence']);
                $color = $confidence > 50 ? '#2e7d32' : ($confidence > 20 ? '#f57c00' : '#d32f2f');
                echo "<li style='margin: 5px 0; color: $color; font-weight: bold;'>";
                echo "{$pred['label']} <span style='font-size: 0.9em; color: #666;'>({$pred['confidence']}%)</span>";
                echo "</li>";
            }
            echo "</ol>";
            echo "</div>";
        }
        
        // Display color analysis if available
        if (!empty($colors) || !empty($colorCharacteristics) || !empty($colorCategories) || isset($colorDescription)) {
            echo "<div style='background: linear-gradient(135deg, #f3e5f5, #e8f4f8); padding: 20px; border: 2px solid #9c27b0; border-radius: 10px; margin: 15px 0; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>";
            echo "<h4 style='margin-top: 0; color: #7b1fa2; display: flex; align-items: center;'>";
            echo "<span style='font-size: 1.5em; margin-right: 10px;'></span> Advanced Color Analysis";
            echo "</h4>";
            
            if (isset($colorDescription)) {
                echo "<div style='background: rgba(255,255,255,0.7); padding: 12px; border-radius: 6px; margin: 10px 0; border-left: 4px solid #7b1fa2;'>";
                echo "<p style='margin: 0; font-style: italic; color: #7b1fa2; font-weight: 500;'>$colorDescription</p>";
                echo "</div>";
            }
            
            // Display color characteristics in a prominent section
            if (!empty($colorCharacteristics)) {
                echo "<div style='background: rgba(255,255,255,0.8); padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #ddd;'>";
                echo "<h5 style='margin-top: 0; color: #5e35b1; display: flex; align-items: center;'>";
                echo "<span style='margin-right: 8px;'>📊</span> Color Profile";
                echo "</h5>";
                echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;'>";
                
                foreach ($colorCharacteristics as $property => $value) {
                    // Add icons and styling for different properties
                    $icon = '';
                    $color = '#5e35b1';
                    switch (strtolower($property)) {
                        case 'brightness':
                            $icon = '☀️';
                            $color = '#ff9800';
                            break;
                        case 'contrast':
                            $icon = '⚫⚪';
                            $color = '#424242';
                            break;
                        case 'saturation':
                            $icon = '🌈';
                            $color = '#e91e63';
                            break;
                        case 'color temperature':
                            $icon = '🌡️';
                            $color = '#2196f3';
                            break;
                        case 'color harmony':
                            $icon = '🎭';
                            $color = '#9c27b0';
                            break;
                        default:
                            $icon = '•';
                    }
                    
                    echo "<div style='background: rgba(255,255,255,0.9); padding: 8px; border-radius: 4px; border-left: 3px solid $color;'>";
                    echo "<strong style='color: $color; font-size: 0.9em;'>$icon $property:</strong><br>";
                    echo "<span style='color: #333; font-weight: 500;'>$value</span>";
                    echo "</div>";
                }
                echo "</div>";
                echo "</div>";
            }
            
            // Display color categories
            if (!empty($colorCategories)) {
                echo "<div style='background: rgba(255,255,255,0.8); padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #ddd;'>";
                echo "<h5 style='margin-top: 0; color: #5e35b1; display: flex; align-items: center;'>";
                echo "<span style='margin-right: 8px;'>🏷️</span> Color Categories";
                echo "</h5>";
                echo "<div style='display: flex; flex-wrap: wrap; gap: 8px;'>";
                
                foreach ($colorCategories as $categoryInfo) {
                    $category = $categoryInfo['category'];
                    $percentage = $categoryInfo['percentage'];
                    
                    // Category-specific styling
                    $bgColor = '#e3f2fd';
                    $textColor = '#1976d2';
                    switch (strtolower($category)) {
                        case 'neutral':
                            $bgColor = '#f5f5f5';
                            $textColor = '#424242';
                            break;
                        case 'primary':
                            $bgColor = '#ffebee';
                            $textColor = '#d32f2f';
                            break;
                        case 'secondary':
                            $bgColor = '#e8f5e8';
                            $textColor = '#388e3c';
                            break;
                        case 'fashion':
                            $bgColor = '#fce4ec';
                            $textColor = '#c2185b';
                            break;
                    }
                    
                    echo "<span style='background: $bgColor; color: $textColor; padding: 6px 12px; border-radius: 15px; font-size: 0.85em; font-weight: 500; border: 1px solid rgba(0,0,0,0.1);'>";
                    echo "$category ($percentage%)";
                    echo "</span>";
                }
                echo "</div>";
                echo "</div>";
            }
            
            if (!empty($colors)) {
                echo "<div style='background: rgba(255,255,255,0.8); padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px solid #ddd;'>";
                echo "<h5 style='margin-top: 0; color: #5e35b1; display: flex; align-items: center;'>";
                echo "<span style='margin-right: 8px;'></span> Dominant Colors";
                echo "</h5>";
                echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;'>";
                
                foreach ($colors as $colorInfo) {
                    $colorName = $colorInfo['color'];
                    $percentage = $colorInfo['percentage'];
                    
                    // Create a color swatch (enhanced color mapping)
                    $colorCode = '';
                    $cleanColorName = strtolower(str_replace([' ', '_'], '', $colorName));
                    
                    switch ($cleanColorName) {
                        case 'red': $colorCode = '#FF0000'; break;
                        case 'blue': $colorCode = '#0000FF'; break;
                        case 'royalblue': $colorCode = '#4169E1'; break;
                        case 'skyblue': $colorCode = '#87CEEB'; break;
                        case 'navy': $colorCode = '#000080'; break;
                        case 'green': $colorCode = '#008000'; break;
                        case 'emerald': $colorCode = '#00C957'; break;
                        case 'mint': $colorCode = '#98FF98'; break;
                        case 'olive': $colorCode = '#808000'; break;
                        case 'yellow': $colorCode = '#FFFF00'; break;
                        case 'gold': $colorCode = '#FFD700'; break;
                        case 'orange': $colorCode = '#FFA500'; break;
                        case 'coral': $colorCode = '#FF7F50'; break;
                        case 'salmon': $colorCode = '#FA8072'; break;
                        case 'purple': $colorCode = '#800080'; break;
                        case 'lavender': $colorCode = '#E6E6FA'; break;
                        case 'pink': $colorCode = '#FFC0CB'; break;
                        case 'rose': $colorCode = '#FF007F'; break;
                        case 'brown': $colorCode = '#8B4513'; break;
                        case 'tan': $colorCode = '#D2B48C'; break;
                        case 'bronze': $colorCode = '#CD7F32'; break;
                        case 'copper': $colorCode = '#B87333'; break;
                        case 'gray': case 'grey': $colorCode = '#808080'; break;
                        case 'charcoal': $colorCode = '#36454F'; break;
                        case 'silver': $colorCode = '#C0C0C0'; break;
                        case 'black': $colorCode = '#000000'; break;
                        case 'white': $colorCode = '#FFFFFF'; break;
                        case 'beige': $colorCode = '#F5F5DC'; break;
                        case 'cream': $colorCode = '#FFFDD0'; break;
                        case 'ivory': $colorCode = '#FFFFF0'; break;
                        case 'khaki': $colorCode = '#F0E68C'; break;
                        case 'teal': $colorCode = '#008080'; break;
                        case 'turquoise': $colorCode = '#40E0D0'; break;
                        case 'burgundy': $colorCode = '#800020'; break;
                        case 'maroon': $colorCode = '#800000'; break;
                        default: $colorCode = '#CCCCCC';
                    }
                    
                    // Add border for white/light colors
                    $borderStyle = ($colorCode === '#FFFFFF' || $colorCode === '#FFFDD0' || $colorCode === '#FFFFF0') ? 
                        'border: 2px solid #ddd;' : 'border: 1px solid rgba(0,0,0,0.2);';
                    
                    echo "<div style='display: flex; align-items: center; background: rgba(255,255,255,0.9); padding: 8px; border-radius: 6px; $borderStyle'>";
                    echo "<div style='width: 24px; height: 24px; background-color: $colorCode; $borderStyle border-radius: 50%; margin-right: 10px; flex-shrink: 0;'></div>";
                    echo "<div style='flex-grow: 1;'>";
                    echo "<div style='color: #333; font-weight: bold; font-size: 0.9em;'>" . ucwords(str_replace('_', ' ', $colorName)) . "</div>";
                    echo "<div style='color: #666; font-size: 0.8em;'>$percentage%</div>";
                    echo "</div>";
                    echo "</div>";
                }
                echo "</div>";
                echo "</div>";
            }
            echo "</div>";
        }
        
        echo "<img src='$targetFile' width='300' />";
    } else {
        echo "Error uploading the image.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload & Predict</title>
</head>
<body>
    <div class="upload-predict-section" style="margin:30px 0; padding:20px; background:#f8f8ff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.07); max-width:500px;">
        <h3 style="margin-top:0; color:#4caf50;">AI Fashion & Color Prediction</h3>
        <form method="POST" action="ml_model/upload_predict.php" enctype="multipart/form-data" target="_blank">
            <label for="image" style="cursor:pointer;display:inline-block;">
                <img src="assets/icons/camera.png" alt="Camera" style="width:48px;height:48px;vertical-align:middle;">
                <span style="font-size:1.1em;vertical-align:middle;">Click to take/upload photo</span>
            </label>
            <input type="file" name="image" id="image" accept="image/*" capture="environment" style="display:none;" required>
            <br><br>
            <button type="button" onclick="document.getElementById('image').click();" style="background:#4caf50;color:#fff;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-size:1em;">
                <img src="assets/icons/camera.png" alt="Camera" style="width:24px;height:24px;vertical-align:middle;margin-right:8px;">
                Take/Upload Photo
            </button>
            <br><br>
            <input type="submit" value="Upload and Predict" style="background:#1976d2;color:#fff;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-size:1em;">
        </form>
    </div>
</body>
</html>
