<?php
// BizFlow Language File

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

$translations = [

    'en' => [
        // General
        'app_name' => 'BizFlow', 'admin_panel' => 'Admin Panel', 'pos_terminal' => 'POS Terminal',
        'save' => 'Save', 'cancel' => 'Cancel', 'confirm' => 'Confirm', 'delete' => 'Delete',
        'edit' => 'Edit', 'add' => 'Add', 'search' => '🔍 Search...', 'loading' => 'Loading...',
        'yes' => 'Yes', 'no' => 'No', 'all' => 'All', 'close' => 'Close', 'logout' => 'Logout',
        'admin' => 'Admin', 'language' => 'Language', 'live' => 'LIVE', 'today' => 'Today',
        'view_all' => 'View All', 'units' => 'Units', 'piece' => 'PIECE', 'cup' => 'CUP',
        
        // Sidebar
        'overview' => 'OVERVIEW', 'sales' => 'SALES', 'inventory' => 'INVENTORY',
        'finance' => 'FINANCE', 'management' => 'MANAGEMENT',
        'dashboard' => 'Dashboard', 'all_sales' => 'All Sales', 'customers' => 'Customers',
        'products' => 'Products', 'categories' => 'Categories', 'suppliers' => 'Suppliers',
        'expenses' => 'Expenses', 'reports' => 'Reports', 'staff_pins' => 'Staff & PINs',
        'settings' => 'Settings',
        
        // Dashboard
        'welcome_back' => 'Welcome back', 'whats_happening' => "Here's what's happening today",
        'todays_revenue' => "TODAY'S REVENUE", 'sales_today' => 'SALES TODAY',
        'monthly_profit' => 'MONTHLY PROFIT', 'low_stock' => 'LOW STOCK',
        'live_label' => 'Live', 'real_time' => 'Real-time',
        'after_expenses' => 'After expenses', 'items_low' => 'Items low',
        'recent_sales' => 'Recent Sales', 'quick_actions' => 'Quick Actions',
        'new_sale' => 'New Sale', 'add_product' => 'Add Product',
        'add_customer' => 'Add Customer', 'add_cashier' => 'Add Cashier',
        'walk_in' => 'Walk-in', 'by' => 'by',
        
        // Sales page
        'sales_history' => 'Sales History', 'coming_soon' => 'Coming soon!',
        
        // Customers page
        'no_customers_yet' => 'No customers yet',
        
        // Products page
        'product' => 'PRODUCT', 'category' => 'CATEGORY', 'price' => 'PRICE',
        'stock' => 'STOCK', 'actions' => 'ACTIONS', 'sku' => 'SKU',
        
        // Categories
        // (already covered above)
        
        // Suppliers
        'no_suppliers_yet' => 'No suppliers yet',
        
        // Expenses
        'this_month' => 'this month', 'no_expenses' => 'No expenses recorded',
        
        // Reports
        'reports_analytics' => 'Reports & Analytics', 'period' => 'Period',
        'last_7_days' => 'Last 7 Days', 'last_30_days' => 'Last 30 Days',
        'last_90_days' => 'Last 90 Days', 'last_year' => 'Last Year',
        'print' => 'Print', 'export_csv' => 'Export CSV',
        'total_revenue' => 'TOTAL REVENUE', 'gross_profit' => 'GROSS PROFIT',
        'total_expenses' => 'TOTAL EXPENSES', 'net_profit' => 'NET PROFIT',
        'avg_transaction' => 'AVG TRANSACTION', 'total_items_sold' => 'TOTAL ITEMS SOLD',
        'from_sales' => 'From sales', 'operating_costs' => 'Operating costs',
        'profit' => 'Profit', 'per_sale' => 'Per sale',
        'daily_revenue_trend' => 'Daily Revenue Trend',
        'top_selling_products' => 'Top Selling Products',
        'top_customers' => 'Top Customers',
        'top_performing_cashiers' => 'Top Performing Cashiers',
        'no_customer_sales' => 'No customer sales yet',
        'dt_profit' => 'DT profit',
    ],

    'ar' => [
        // General
        'app_name' => 'بيزفلو', 'admin_panel' => 'لوحة الإدارة', 'pos_terminal' => 'نقطة البيع',
        'save' => 'حفظ', 'cancel' => 'إلغاء', 'confirm' => 'تأكيد', 'delete' => 'حذف',
        'edit' => 'تعديل', 'add' => 'إضافة', 'search' => '🔍 بحث...', 'loading' => 'جاري التحميل...',
        'yes' => 'نعم', 'no' => 'لا', 'all' => 'الكل', 'close' => 'إغلاق', 'logout' => 'خروج',
        'admin' => 'الإدارة', 'language' => 'اللغة', 'live' => 'مباشر', 'today' => 'اليوم',
        'view_all' => 'عرض الكل', 'units' => 'وحدات', 'piece' => 'قطعة', 'cup' => 'كوب',
        
        // Sidebar
        'overview' => 'نظرة عامة', 'sales' => 'المبيعات', 'inventory' => 'المخزون',
        'finance' => 'المالية', 'management' => 'الإدارة',
        'dashboard' => 'لوحة التحكم', 'all_sales' => 'كل المبيعات', 'customers' => 'الزبائن',
        'products' => 'المنتجات', 'categories' => 'الفئات', 'suppliers' => 'الموردون',
        'expenses' => 'المصاريف', 'reports' => 'التقارير', 'staff_pins' => 'الموظفون والرموز',
        'settings' => 'الإعدادات',
        
        // Dashboard
        'welcome_back' => 'مرحباً بعودتك', 'whats_happening' => 'إليك ما يحدث اليوم',
        'todays_revenue' => 'إيرادات اليوم', 'sales_today' => 'مبيعات اليوم',
        'monthly_profit' => 'الأرباح الشهرية', 'low_stock' => 'مخزون منخفض',
        'live_label' => 'مباشر', 'real_time' => 'فوري',
        'after_expenses' => 'بعد المصاريف', 'items_low' => 'منتجات قليلة',
        'recent_sales' => 'المبيعات الأخيرة', 'quick_actions' => 'إجراءات سريعة',
        'new_sale' => 'بيع جديد', 'add_product' => 'إضافة منتج',
        'add_customer' => 'إضافة زبون', 'add_cashier' => 'إضافة كاشير',
        'walk_in' => 'زبون عابر', 'by' => 'بواسطة',
        
        // Sales page
        'sales_history' => 'سجل المبيعات', 'coming_soon' => 'قريباً!',
        
        // Customers page
        'no_customers_yet' => 'لا يوجد زبائن بعد',
        
        // Products page
        'product' => 'المنتج', 'category' => 'الفئة', 'price' => 'السعر',
        'stock' => 'المخزون', 'actions' => 'الإجراءات', 'sku' => 'الرمز',
        
        // Suppliers
        'no_suppliers_yet' => 'لا يوجد موردون بعد',
        
        // Expenses
        'this_month' => 'هذا الشهر', 'no_expenses' => 'لا توجد مصاريف مسجلة',
        
        // Reports
        'reports_analytics' => 'التقارير والتحليلات', 'period' => 'الفترة',
        'last_7_days' => 'آخر 7 أيام', 'last_30_days' => 'آخر 30 يوم',
        'last_90_days' => 'آخر 90 يوم', 'last_year' => 'العام الماضي',
        'print' => 'طباعة', 'export_csv' => 'تصدير CSV',
        'total_revenue' => 'إجمالي الإيرادات', 'gross_profit' => 'الربح الإجمالي',
        'total_expenses' => 'إجمالي المصاريف', 'net_profit' => 'صافي الربح',
        'avg_transaction' => 'متوسط المعاملة', 'total_items_sold' => 'إجمالي العناصر المباعة',
        'from_sales' => 'من المبيعات', 'operating_costs' => 'تكاليف التشغيل',
        'profit' => 'ربح', 'per_sale' => 'لكل بيع',
        'daily_revenue_trend' => 'اتجاه الإيرادات اليومية',
        'top_selling_products' => 'المنتجات الأكثر مبيعاً',
        'top_customers' => 'أفضل الزبائن',
        'top_performing_cashiers' => 'أفضل الكاشيرات أداءً',
        'no_customer_sales' => 'لا توجد مبيعات للزبائن بعد',
        'dt_profit' => 'دينار ربح',
    ],

    'fr' => [
        // General
        'app_name' => 'BizFlow', 'admin_panel' => 'Panneau Admin', 'pos_terminal' => 'Point de Vente',
        'save' => 'Enregistrer', 'cancel' => 'Annuler', 'confirm' => 'Confirmer', 'delete' => 'Supprimer',
        'edit' => 'Modifier', 'add' => 'Ajouter', 'search' => '🔍 Rechercher...', 'loading' => 'Chargement...',
        'yes' => 'Oui', 'no' => 'Non', 'all' => 'Tout', 'close' => 'Fermer', 'logout' => 'Déconnexion',
        'admin' => 'Admin', 'language' => 'Langue', 'live' => 'EN DIRECT', 'today' => "Aujourd'hui",
        'view_all' => 'Voir tout', 'units' => 'Unités', 'piece' => 'PIÈCE', 'cup' => 'TASSE',
        
        // Sidebar
        'overview' => 'APERÇU', 'sales' => 'VENTES', 'inventory' => 'INVENTAIRE',
        'finance' => 'FINANCES', 'management' => 'GESTION',
        'dashboard' => 'Tableau de bord', 'all_sales' => 'Toutes les ventes', 'customers' => 'Clients',
        'products' => 'Produits', 'categories' => 'Catégories', 'suppliers' => 'Fournisseurs',
        'expenses' => 'Dépenses', 'reports' => 'Rapports', 'staff_pins' => 'Personnel & PINs',
        'settings' => 'Paramètres',
        
        // Dashboard
        'welcome_back' => 'Bon retour', 'whats_happening' => "Voici ce qui se passe aujourd'hui",
        'todays_revenue' => "REVENU DU JOUR", 'sales_today' => 'VENTES DU JOUR',
        'monthly_profit' => 'BÉNÉFICE MENSUEL', 'low_stock' => 'STOCK FAIBLE',
        'live_label' => 'En direct', 'real_time' => 'Temps réel',
        'after_expenses' => 'Après dépenses', 'items_low' => 'Articles faibles',
        'recent_sales' => 'Ventes récentes', 'quick_actions' => 'Actions rapides',
        'new_sale' => 'Nouvelle vente', 'add_product' => 'Ajouter produit',
        'add_customer' => 'Ajouter client', 'add_cashier' => 'Ajouter caissier',
        'walk_in' => 'Client occasionnel', 'by' => 'par',
        
        // Sales page
        'sales_history' => 'Historique des ventes', 'coming_soon' => 'Bientôt disponible !',
        
        // Customers page
        'no_customers_yet' => 'Aucun client pour le moment',
        
        // Products page
        'product' => 'PRODUIT', 'category' => 'CATÉGORIE', 'price' => 'PRIX',
        'stock' => 'STOCK', 'actions' => 'ACTIONS', 'sku' => 'SKU',
        
        // Suppliers
        'no_suppliers_yet' => 'Aucun fournisseur pour le moment',
        
        // Expenses
        'this_month' => 'ce mois', 'no_expenses' => 'Aucune dépense enregistrée',
        
        // Reports
        'reports_analytics' => 'Rapports & Analyses', 'period' => 'Période',
        'last_7_days' => '7 derniers jours', 'last_30_days' => '30 derniers jours',
        'last_90_days' => '90 derniers jours', 'last_year' => "L'année dernière",
        'print' => 'Imprimer', 'export_csv' => 'Exporter CSV',
        'total_revenue' => 'REVENU TOTAL', 'gross_profit' => 'BÉNÉFICE BRUT',
        'total_expenses' => 'DÉPENSES TOTALES', 'net_profit' => 'BÉNÉFICE NET',
        'avg_transaction' => 'TRANSACTION MOY.', 'total_items_sold' => 'ARTICLES VENDUS',
        'from_sales' => 'Des ventes', 'operating_costs' => "Coûts d'exploitation",
        'profit' => 'Bénéfice', 'per_sale' => 'Par vente',
        'daily_revenue_trend' => 'Tendance des revenus quotidiens',
        'top_selling_products' => 'Produits les plus vendus',
        'top_customers' => 'Meilleurs clients',
        'top_performing_cashiers' => 'Meilleurs caissiers',
        'no_customer_sales' => 'Aucune vente client',
        'dt_profit' => 'DT bénéfice',
    ],
];

function __($key) {
    global $translations, $currentLang;
    return $translations[$currentLang][$key] ?? $translations['en'][$key] ?? $key;
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
    global $translations, $currentLang;
    $json = json_encode($translations[$currentLang], JSON_UNESCAPED_UNICODE);
    return '<script>var LANG = ' . $json . ';</script>';
}
?>
