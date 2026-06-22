<?php
// ========================================
// 💼 BIZFLOW - Theme System
// Multi-tenant theme support
// ========================================

if (!function_exists('renderThemeCSS')) {

    /**
     * Load theme for current business
     */
    function loadCurrentTheme() {
        global $conn;
        $bid = getBusinessId();
        
        if ($bid <= 0) {
            // Default theme for non-logged users
            return getDefaultTheme();
        }
        
        $stmt = $conn->prepare("SELECT * FROM business_themes WHERE business_id = ?");
        $stmt->bind_param("i", $bid);
        $stmt->execute();
        $theme = $stmt->get_result()->fetch_assoc();
        
        if (!$theme) {
            // Create default
            $conn->query("INSERT INTO business_themes (business_id) VALUES ($bid)");
            $stmt->execute();
            $theme = $stmt->get_result()->fetch_assoc();
        }
        
        return $theme;
    }
    
    /**
     * Default theme (BizFlow blue)
     */
    function getDefaultTheme() {
        return [
            'logo_emoji' => '💼',
            'brand_name' => 'BizFlow',
            'tagline' => 'Business Manager',
            'primary_color' => '#3b82f6',
            'secondary_color' => '#60a5fa',
            'bg_dark' => '#0a0e1a',
            'bg_card' => '#1a1f33',
            'text_color' => '#ffffff',
            'accent_color' => '#10b981',
            'font_heading' => 'Playfair Display',
            'font_body' => 'Inter',
            'custom_css' => ''
        ];
    }
    
    /**
     * Render theme CSS variables + Google Fonts
     */
    function renderThemeCSS($theme = null) {
        if (!$theme) $theme = loadCurrentTheme();
        
        $heading = htmlspecialchars($theme['font_heading'] ?? 'Playfair Display');
        $body = htmlspecialchars($theme['font_body'] ?? 'Inter');
        
        $primary = $theme['primary_color'] ?? '#3b82f6';
        $secondary = $theme['secondary_color'] ?? '#60a5fa';
        $bgDark = $theme['bg_dark'] ?? '#0a0e1a';
        $bgCard = $theme['bg_card'] ?? '#1a1f33';
        $text = $theme['text_color'] ?? '#ffffff';
        $accent = $theme['accent_color'] ?? '#10b981';
        
        $css = "<link href='https://fonts.googleapis.com/css2?family=" . urlencode($heading) . ":wght@400;600;700;800;900&family=" . urlencode($body) . ":wght@400;500;600;700;800;900&display=swap' rel='stylesheet'>";
        
        $css .= "<style id='bizflow-theme'>
            :root {
                --primary: {$primary};
                --secondary: {$secondary};
                --bg-dark: {$bgDark};
                --bg-card: {$bgCard};
                --text: {$text};
                --accent: {$accent};
                --font-heading: '{$heading}', serif;
                --font-body: '{$body}', sans-serif;
            }
            
            body {
                background: var(--bg-dark);
                color: var(--text);
                font-family: var(--font-body);
            }
            
            h1, h2, h3, h4, h5, h6, .heading {
                font-family: var(--font-heading);
            }
        ";
        
        // Custom CSS from theme
        if (!empty($theme['custom_css'])) {
            $css .= "\n/* Custom CSS */\n" . $theme['custom_css'];
        }
        
        $css .= "</style>";
        
        return $css;
    }
    
    /**
     * Get business logo HTML
     */
    function renderLogo($size = 'medium') {
        $theme = loadCurrentTheme();
        
        $sizes = [
            'small' => '36px',
            'medium' => '50px',
            'large' => '80px',
            'xl' => '120px'
        ];
        $s = $sizes[$size] ?? '50px';
        
        $emoji = $theme['logo_emoji'] ?? '💼';
        $url = $theme['logo_url'] ?? null;
        
        if ($url) {
            return "<img src='" . htmlspecialchars($url) . "' style='width:{$s};height:{$s};border-radius:14px;object-fit:cover;'>";
        }
        
        $fontSize = intval($s) * 0.5;
        return "<div style='width:{$s};height:{$s};background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:{$fontSize}px;'>{$emoji}</div>";
    }
    
    /**
     * Get brand name HTML
     */
    function renderBrandName($showTagline = true) {
        $theme = loadCurrentTheme();
        $brand = htmlspecialchars($theme['brand_name'] ?? 'BizFlow');
        $tagline = htmlspecialchars($theme['tagline'] ?? 'Business Manager');
        
        // Smart split for "BizFlow" → "Biz" + "Flow"
        if (strlen($brand) > 3) {
            $mid = intval(strlen($brand) / 2);
            $part1 = substr($brand, 0, $mid);
            $part2 = substr($brand, $mid);
            $brandHtml = "{$part1}<span style='color:var(--primary)'>{$part2}</span>";
        } else {
            $brandHtml = $brand;
        }
        
        $html = "<div style='font-family:var(--font-heading);font-size:22px;font-weight:700;'>{$brandHtml}</div>";
        
        if ($showTagline) {
            $html .= "<div style='font-size:11px;color:#9ca3af;'>{$tagline}</div>";
        }
        
        return $html;
    }
}
?>
