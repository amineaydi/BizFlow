<?php
// BizFlow - Auto Translation System

function getCurrentLang() {
    $allowed = ['en', 'ar', 'fr'];
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $allowed)) {
        return $_SESSION['lang'];
    }
    if (isset($_COOKIE['bizflow_lang']) && in_array($_COOKIE['bizflow_lang'], $allowed)) {
        $_SESSION['lang'] = $_COOKIE['bizflow_lang'];
        return $_COOKIE['bizflow_lang'];
    }
    return 'en';
}

function setLang($lang) {
    $allowed = ['en', 'ar', 'fr'];
    if (!in_array($lang, $allowed)) $lang = 'en';
    $_SESSION['lang'] = $lang;
    setcookie('bizflow_lang', $lang, time() + (365 * 24 * 60 * 60), '/');
    return $lang;
}

if (isset($_GET['set_lang'])) {
    setLang($_GET['set_lang']);
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirect");
    exit;
}

$currentLang = getCurrentLang();

$langMeta = [
    'en' => ['flag' => '🇬🇧', 'code' => 'EN', 'dir' => 'ltr', 'name' => 'English'],
    'ar' => ['flag' => '🇸🇦', 'code' => 'AR', 'dir' => 'rtl', 'name' => 'العربية'],
    'fr' => ['flag' => '🇫🇷', 'code' => 'FR', 'dir' => 'ltr', 'name' => 'Français'],
];

// ============================================================
// 🌍 AUTO TRANSLATION DICTIONARY
// Format: 'English Text' => ['ar' => 'Arabic', 'fr' => 'French']
// The system will automatically replace English text on the page!
// ============================================================
$autoTranslate = [

    // ===== Sidebar Sections =====
    'OVERVIEW'             => ['ar' => 'نظرة عامة',      'fr' => 'APERÇU'],
    'SALES'                => ['ar' => 'المبيعات',       'fr' => 'VENTES'],
    'INVENTORY'            => ['ar' => 'المخزون',        'fr' => 'INVENTAIRE'],
    'FINANCE'              => ['ar' => 'المالية',        'fr' => 'FINANCES'],
    'MANAGEMENT'           => ['ar' => 'الإدارة',        'fr' => 'GESTION'],
    
    // ===== Sidebar Items =====
    'Dashboard'            => ['ar' => 'لوحة التحكم',    'fr' => 'Tableau de bord'],
    'All Sales'            => ['ar' => 'كل المبيعات',    'fr' => 'Toutes les ventes'],
    'Customers'            => ['ar' => 'الزبائن',        'fr' => 'Clients'],
    'Products'             => ['ar' => 'المنتجات',       'fr' => 'Produits'],
    'Categories'           => ['ar' => 'الفئات',         'fr' => 'Catégories'],
    'Suppliers'            => ['ar' => 'الموردون',       'fr' => 'Fournisseurs'],
    'Expenses'             => ['ar' => 'المصاريف',       'fr' => 'Dépenses'],
    'Reports'              => ['ar' => 'التقارير',       'fr' => 'Rapports'],
    'Staff & PINs'         => ['ar' => 'الموظفون والرموز','fr' => 'Personnel & PINs'],
    'Settings'             => ['ar' => 'الإعدادات',      'fr' => 'Paramètres'],
    'Admin Panel'          => ['ar' => 'لوحة الإدارة',   'fr' => 'Panneau Admin'],
    'POS Terminal'         => ['ar' => 'نقطة البيع',     'fr' => 'Point de Vente'],
    
    // ===== Dashboard =====
    'Welcome back'         => ['ar' => 'مرحباً بعودتك',  'fr' => 'Bon retour'],
    "Here's what's happening today" => ['ar' => 'إليك ما يحدث اليوم', 'fr' => "Voici ce qui se passe aujourd'hui"],
    "TODAY'S REVENUE"      => ['ar' => 'إيرادات اليوم',  'fr' => 'REVENU DU JOUR'],
    'SALES TODAY'          => ['ar' => 'مبيعات اليوم',   'fr' => 'VENTES DU JOUR'],
    'MONTHLY PROFIT'       => ['ar' => 'الأرباح الشهرية','fr' => 'BÉNÉFICE MENSUEL'],
    'LOW STOCK'            => ['ar' => 'مخزون منخفض',    'fr' => 'STOCK FAIBLE'],
    'Real-time'            => ['ar' => 'فوري',           'fr' => 'Temps réel'],
    'After expenses'       => ['ar' => 'بعد المصاريف',   'fr' => 'Après dépenses'],
    'Items low'            => ['ar' => 'منتجات قليلة',   'fr' => 'Articles faibles'],
    'Live'                 => ['ar' => 'مباشر',          'fr' => 'En direct'],
    'LIVE'                 => ['ar' => 'مباشر',          'fr' => 'EN DIRECT'],
    'Today:'               => ['ar' => 'اليوم:',         'fr' => "Aujourd'hui:"],
    'Recent Sales'         => ['ar' => 'المبيعات الأخيرة','fr' => 'Ventes récentes'],
    'Quick Actions'        => ['ar' => 'إجراءات سريعة',  'fr' => 'Actions rapides'],
    'View All'             => ['ar' => 'عرض الكل',       'fr' => 'Voir tout'],
    'New Sale'             => ['ar' => 'بيع جديد',       'fr' => 'Nouvelle vente'],
    'Add Product'          => ['ar' => 'إضافة منتج',     'fr' => 'Ajouter produit'],
    'Add Customer'         => ['ar' => 'إضافة زبون',     'fr' => 'Ajouter client'],
    'Add Cashier'          => ['ar' => 'إضافة كاشير',    'fr' => 'Ajouter caissier'],
    'Walk-in'              => ['ar' => 'زبون عابر',      'fr' => 'Client occasionnel'],
    'by'                   => ['ar' => 'بواسطة',         'fr' => 'par'],
    
    // ===== Sales Page =====
    'Sales History'        => ['ar' => 'سجل المبيعات',   'fr' => 'Historique des ventes'],
    'Coming soon!'         => ['ar' => 'قريباً!',         'fr' => 'Bientôt disponible !'],
    
    // ===== Empty States =====
    'No customers yet'     => ['ar' => 'لا يوجد زبائن بعد', 'fr' => 'Aucun client pour le moment'],
    'No suppliers yet'     => ['ar' => 'لا يوجد موردون بعد','fr' => 'Aucun fournisseur pour le moment'],
    'No expenses recorded' => ['ar' => 'لا توجد مصاريف مسجلة','fr' => 'Aucune dépense enregistrée'],
    'No customer sales yet'=> ['ar' => 'لا توجد مبيعات للزبائن','fr' => 'Aucune vente client'],
    'No products found'    => ['ar' => 'لا توجد منتجات', 'fr' => 'Aucun produit trouvé'],
    
    // ===== Products Page =====
    'PRODUCT'              => ['ar' => 'المنتج',         'fr' => 'PRODUIT'],
    'CATEGORY'             => ['ar' => 'الفئة',          'fr' => 'CATÉGORIE'],
    'PRICE'                => ['ar' => 'السعر',          'fr' => 'PRIX'],
    'STOCK'                => ['ar' => 'المخزون',        'fr' => 'STOCK'],
    'ACTIONS'              => ['ar' => 'الإجراءات',      'fr' => 'ACTIONS'],
    'ADD'                  => ['ar' => 'إضافة',          'fr' => 'AJOUTER'],
    'Delete'               => ['ar' => 'حذف',            'fr' => 'Supprimer'],
    'Edit'                 => ['ar' => 'تعديل',          'fr' => 'Modifier'],
    'PIECE'                => ['ar' => 'قطعة',           'fr' => 'PIÈCE'],
    'CUP'                  => ['ar' => 'كوب',            'fr' => 'TASSE'],
    
    // ===== Expenses =====
    'this month'           => ['ar' => 'هذا الشهر',      'fr' => 'ce mois'],
    
    // ===== Reports =====
    'Reports & Analytics'  => ['ar' => 'التقارير والتحليلات','fr' => 'Rapports & Analyses'],
    'Period:'              => ['ar' => 'الفترة:',        'fr' => 'Période :'],
    'Last 7 Days'          => ['ar' => 'آخر 7 أيام',     'fr' => '7 derniers jours'],
    'Last 30 Days'         => ['ar' => 'آخر 30 يوم',     'fr' => '30 derniers jours'],
    'Last 90 Days'         => ['ar' => 'آخر 90 يوم',     'fr' => '90 derniers jours'],
    'Last Year'            => ['ar' => 'العام الماضي',   'fr' => "L'année dernière"],
    'Print'                => ['ar' => 'طباعة',          'fr' => 'Imprimer'],
    'Export CSV'           => ['ar' => 'تصدير CSV',      'fr' => 'Exporter CSV'],
    'TOTAL REVENUE'        => ['ar' => 'إجمالي الإيرادات','fr' => 'REVENU TOTAL'],
    'GROSS PROFIT'         => ['ar' => 'الربح الإجمالي', 'fr' => 'BÉNÉFICE BRUT'],
    'TOTAL EXPENSES'       => ['ar' => 'إجمالي المصاريف','fr' => 'DÉPENSES TOTALES'],
    'NET PROFIT'           => ['ar' => 'صافي الربح',     'fr' => 'BÉNÉFICE NET'],
    'AVG TRANSACTION'      => ['ar' => 'متوسط المعاملة', 'fr' => 'TRANSACTION MOY.'],
    'TOTAL ITEMS SOLD'     => ['ar' => 'إجمالي العناصر المباعة','fr' => 'ARTICLES VENDUS'],
    'From sales'           => ['ar' => 'من المبيعات',    'fr' => 'Des ventes'],
    'Operating costs'      => ['ar' => 'تكاليف التشغيل', 'fr' => "Coûts d'exploitation"],
    'Profit'               => ['ar' => 'ربح',            'fr' => 'Bénéfice'],
    'Per sale'             => ['ar' => 'لكل بيع',        'fr' => 'Par vente'],
    'Units'                => ['ar' => 'وحدات',          'fr' => 'Unités'],
    'Daily Revenue Trend'  => ['ar' => 'اتجاه الإيرادات اليومية','fr' => 'Tendance des revenus quotidiens'],
    'Top Selling Products' => ['ar' => 'المنتجات الأكثر مبيعاً','fr' => 'Produits les plus vendus'],
    'Top Customers'        => ['ar' => 'أفضل الزبائن',   'fr' => 'Meilleurs clients'],
    'Top Performing Cashiers' => ['ar' => 'أفضل الكاشيرات أداءً','fr' => 'Meilleurs caissiers'],
    'units'                => ['ar' => 'وحدات',          'fr' => 'unités'],
    'sales'                => ['ar' => 'مبيعات',         'fr' => 'ventes'],
    'DT profit'            => ['ar' => 'دينار ربح',      'fr' => 'DT bénéfice'],
    
    // ===== POS =====
    'Cart'                 => ['ar' => 'السلة',          'fr' => 'Panier'],
    'Cart is empty'        => ['ar' => 'السلة فارغة',    'fr' => 'Le panier est vide'],
    'Click products to add'=> ['ar' => 'انقر على المنتجات للإضافة','fr' => 'Cliquez sur les produits'],
    'Clear'                => ['ar' => 'مسح',            'fr' => 'Vider'],
    'Subtotal'             => ['ar' => 'المجموع الفرعي', 'fr' => 'Sous-total'],
    'Tax'                  => ['ar' => 'الضريبة',        'fr' => 'Taxe'],
    'TOTAL'                => ['ar' => 'المجموع',        'fr' => 'TOTAL'],
    'Cash'                 => ['ar' => 'نقدي',           'fr' => 'Espèces'],
    'Card'                 => ['ar' => 'بطاقة',          'fr' => 'Carte'],
    'Checkout'             => ['ar' => 'الدفع',          'fr' => 'Paiement'],
    'Select Customer'      => ['ar' => 'اختيار زبون',    'fr' => 'Sélectionner un client'],
    'Complete Sale'        => ['ar' => 'إتمام البيع',    'fr' => 'Finaliser la vente'],
    'Total to pay:'        => ['ar' => 'المبلغ المطلوب:','fr' => 'Total à payer :'],
    'Cash Received'        => ['ar' => 'المبلغ المستلم', 'fr' => 'Montant reçu'],
    'Change'               => ['ar' => 'الباقي',         'fr' => 'Monnaie'],
    'Confirm'              => ['ar' => 'تأكيد',          'fr' => 'Confirmer'],
    'Cancel'               => ['ar' => 'إلغاء',          'fr' => 'Annuler'],
    'Sale Complete!'       => ['ar' => 'تمت عملية البيع!','fr' => 'Vente terminée !'],
    'Invoice:'             => ['ar' => 'الفاتورة:',      'fr' => 'Facture :'],
    'Save'                 => ['ar' => 'حفظ',            'fr' => 'Enregistrer'],
    'Close'                => ['ar' => 'إغلاق',          'fr' => 'Fermer'],
    'Search...'            => ['ar' => 'بحث...',         'fr' => 'Rechercher...'],
    'Logout'               => ['ar' => 'خروج',           'fr' => 'Déconnexion'],
    'Admin'                => ['ar' => 'الإدارة',        'fr' => 'Admin'],
    'All'                  => ['ar' => 'الكل',           'fr' => 'Tout'],
    'OUT'                  => ['ar' => 'نفذ',            'fr' => 'ÉPUISÉ'],
];

// ============================================================
// ✅ AUTO TRANSLATE - Replaces all English text automatically
// ============================================================
function autoTranslatePage($html) {
    global $autoTranslate, $currentLang;
    
    if ($currentLang === 'en') return $html; // No translation needed
    
    // Sort by length (longest first) to avoid partial replacements
    uksort($autoTranslate, function($a, $b) {
        return strlen($b) - strlen($a);
    });
    
    foreach ($autoTranslate as $english => $translations) {
        if (!isset($translations[$currentLang])) continue;
        
        $translated = $translations[$currentLang];
        
        // Replace inside HTML text content (not attributes)
        // Use word boundary to avoid partial matches
        $pattern = '/(?<![a-zA-Z])' . preg_quote($english, '/') . '(?![a-zA-Z])/u';
        $html = preg_replace($pattern, $translated, $html);
    }
    
    return $html;
}

// ============================================================
// ✅ Start output buffer - captures all output for translation
// ============================================================
function startAutoTranslate() {
    ob_start();
}

// ============================================================
// ✅ End buffer + apply translations + inject RTL/dir
// ============================================================
function endAutoTranslate() {
    global $currentLang;
    $html = ob_get_clean();
    
    // Auto translate
    $html = autoTranslatePage($html);
    
    // Auto add dir="rtl" or "ltr" to <html> tag
    $dir = getLangDir();
    $html = preg_replace(
        '/<html\s*([^>]*)>/i',
        '<html lang="' . $currentLang . '" dir="' . $dir . '" $1>',
        $html,
        1
    );
    
    echo $html;
}

// ============================================================
// ✅ Helper functions
// ============================================================
function __($key) {
    global $autoTranslate, $currentLang;
    if ($currentLang === 'en') return $key;
    return $autoTranslate[$key][$currentLang] ?? $key;
}

function getLangDir() {
    global $langMeta, $currentLang;
    return $langMeta[$currentLang]['dir'] ?? 'ltr';
}

function renderLangSwitcher() {
    global $langMeta, $currentLang;
    $current = $langMeta[$currentLang];
    
    $html = '<div class="lang-switcher">';
    $html .= '<button type="button" class="lang-btn" id="langToggle">';
    $html .= '<span>' . $current['flag'] . '</span>';
    $html .= '<span>' . $current['code'] . '</span>';
    $html .= '<span style="font-size:10px;opacity:0.6;">▼</span>';
    $html .= '</button>';
    $html .= '<div class="lang-dropdown" id="langDropdown">';
    
    foreach ($langMeta as $code => $meta) {
        $active = ($code === $currentLang) ? ' active' : '';
        $check = ($code === $currentLang) ? '✓' : '';
        $url = '?set_lang=' . $code;
        $html .= '<a href="' . $url . '" class="lang-option' . $active . '">';
        $html .= '<span class="flag">' . $meta['flag'] . '</span>';
        $html .= '<span>' . $meta['name'] . '</span>';
        $html .= '<span class="check">' . $check . '</span>';
        $html .= '</a>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

function renderLangJS() {
    global $autoTranslate, $currentLang;
    $simple = [];
    foreach ($autoTranslate as $en => $translations) {
        $simple[$en] = $translations[$currentLang] ?? $en;
    }
    $json = json_encode($simple, JSON_UNESCAPED_UNICODE);
    return '<script>var LANG = ' . $json . ';</script>';
}

// ============================================================
// ✅ AUTO-START translation buffer
// ============================================================
startAutoTranslate();

// Register shutdown to apply translation automatically
register_shutdown_function('endAutoTranslate');
?>
