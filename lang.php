<?php
// ============================================================
// 🌍 BizFlow - Language File
// Supports: English (en), Arabic (ar), French (fr)
// Used by: POS + Admin + Super Admin
// ============================================================

// ============================================================
// ✅ Get current language from session or cookie
// ============================================================
function getCurrentLang() {
    $allowed = ['en', 'ar', 'fr'];
    
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $allowed)) {
        return $_SESSION['lang'];
    }
    if (isset($_COOKIE['bizflow_lang']) && in_array($_COOKIE['bizflow_lang'], $allowed)) {
        $_SESSION['lang'] = $_COOKIE['bizflow_lang'];
        return $_COOKIE['bizflow_lang'];
    }
    return 'en'; // default
}

// ============================================================
// ✅ Set language (save in session + cookie 1 year)
// ============================================================
function setLang($lang) {
    $allowed = ['en', 'ar', 'fr'];
    if (!in_array($lang, $allowed)) $lang = 'en';
    $_SESSION['lang'] = $lang;
    setcookie('bizflow_lang', $lang, time() + (365 * 24 * 60 * 60), '/');
    return $lang;
}

// ============================================================
// ✅ Handle language switch request via URL ?set_lang=ar
// ============================================================
if (isset($_GET['set_lang'])) {
    setLang($_GET['set_lang']);
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirect");
    exit;
}

$currentLang = getCurrentLang();

// ============================================================
// ✅ Language metadata
// ============================================================
$langMeta = [
    'en' => ['flag' => '🇬🇧', 'code' => 'EN', 'dir' => 'ltr', 'name' => 'English'],
    'ar' => ['flag' => '🇸🇦', 'code' => 'AR', 'dir' => 'rtl', 'name' => 'العربية'],
    'fr' => ['flag' => '🇫🇷', 'code' => 'FR', 'dir' => 'ltr', 'name' => 'Français'],
];

// ============================================================
// ✅ All translations
// ============================================================
$translations = [

    // ============================================================
    // 🇬🇧 ENGLISH
    // ============================================================
    'en' => [
        // --- General ---
        'app_name'           => 'BizFlow',
        'pos_terminal'       => 'POS Terminal',
        'admin_panel'        => 'Admin Panel',
        'save'               => 'Save',
        'cancel'             => 'Cancel',
        'confirm'            => 'Confirm',
        'delete'             => 'Delete',
        'edit'               => 'Edit',
        'add'                => 'Add',
        'search'             => '🔍 Search...',
        'loading'            => 'Loading...',
        'yes'                => 'Yes',
        'no'                 => 'No',
        'all'                => 'All',
        'close'              => 'Close',
        'logout'             => 'Logout',
        'admin'              => 'Admin',
        'language'           => 'Language',

        // --- POS ---
        'search_products'    => '🔍 Search products...',
        'cart'               => 'Cart',
        'clear'              => 'Clear',
        'add_customer'       => '👤 Add Customer (optional)',
        'cart_empty'         => 'Cart is empty',
        'click_to_add'       => 'Click products to add',
        'subtotal'           => 'Subtotal',
        'tax'                => 'Tax',
        'total'              => 'TOTAL',
        'cash'               => 'Cash',
        'card'               => 'Card',
        'checkout'           => 'Checkout',
        'select_customer'    => 'Select Customer',
        'complete_sale'      => 'Complete Sale',
        'total_to_pay'       => 'Total to pay:',
        'cash_received'      => 'Cash Received',
        'change'             => 'Change',
        'sale_complete'      => 'Sale Complete!',
        'invoice'            => 'Invoice',
        'new_sale'           => 'New Sale',
        'no_products'        => 'No products found',
        'no_customers'       => 'No customers found',
        'no_more_stock'      => '⚠️ No more stock',
        'stock_limit'        => '⚠️ Stock limit reached',
        'clear_cart_confirm' => 'Clear cart?',
        'insufficient'       => '⚠️ Insufficient amount',
        'server_error'       => '❌ Server error',
        'network_error'      => '❌ Network error',
        'sale_failed'        => 'Sale failed',
        'out'                => 'OUT',
    ],

    // ============================================================
    // 🇸🇦 ARABIC
    // ============================================================
    'ar' => [
        // --- General ---
        'app_name'           => 'بيزفلو',
        'pos_terminal'       => 'نقطة البيع',
        'admin_panel'        => 'لوحة الإدارة',
        'save'               => 'حفظ',
        'cancel'             => 'إلغاء',
        'confirm'            => 'تأكيد',
        'delete'             => 'حذف',
        'edit'               => 'تعديل',
        'add'                => 'إضافة',
        'search'             => '🔍 بحث...',
        'loading'            => 'جاري التحميل...',
        'yes'                => 'نعم',
        'no'                 => 'لا',
        'all'                => 'الكل',
        'close'              => 'إغلاق',
        'logout'             => 'تسجيل الخروج',
        'admin'              => 'الإدارة',
        'language'           => 'اللغة',

        // --- POS ---
        'search_products'    => '🔍 البحث عن المنتجات...',
        'cart'               => 'السلة',
        'clear'              => 'مسح',
        'add_customer'       => '👤 إضافة زبون (اختياري)',
        'cart_empty'         => 'السلة فارغة',
        'click_to_add'       => 'انقر على المنتجات للإضافة',
        'subtotal'           => 'المجموع الفرعي',
        'tax'                => 'الضريبة',
        'total'              => 'المجموع',
        'cash'               => 'نقدي',
        'card'               => 'بطاقة',
        'checkout'           => 'الدفع',
        'select_customer'    => 'اختيار زبون',
        'complete_sale'      => 'إتمام البيع',
        'total_to_pay'       => 'المبلغ المطلوب:',
        'cash_received'      => 'المبلغ المستلم',
        'change'             => 'الباقي',
        'sale_complete'      => 'تمت عملية البيع!',
        'invoice'            => 'الفاتورة',
        'new_sale'           => 'بيع جديد',
        'no_products'        => 'لا توجد منتجات',
        'no_customers'       => 'لا يوجد زبائن',
        'no_more_stock'      => '⚠️ لا يوجد مخزون إضافي',
        'stock_limit'        => '⚠️ تم الوصول لحد المخزون',
        'clear_cart_confirm' => 'مسح السلة؟',
        'insufficient'       => '⚠️ المبلغ غير كافي',
        'server_error'       => '❌ خطأ في الخادم',
        'network_error'      => '❌ خطأ في الشبكة',
        'sale_failed'        => 'فشلت عملية البيع',
        'out'                => 'نفذ',
    ],

    // ============================================================
    // 🇫🇷 FRENCH
    // ============================================================
    'fr' => [
        // --- General ---
        'app_name'           => 'BizFlow',
        'pos_terminal'       => 'Point de Vente',
        'admin_panel'        => 'Panneau Admin',
        'save'               => 'Enregistrer',
        'cancel'             => 'Annuler',
        'confirm'            => 'Confirmer',
        'delete'             => 'Supprimer',
        'edit'               => 'Modifier',
        'add'                => 'Ajouter',
        'search'             => '🔍 Rechercher...',
        'loading'            => 'Chargement...',
        'yes'                => 'Oui',
        'no'                 => 'Non',
        'all'                => 'Tout',
        'close'              => 'Fermer',
        'logout'             => 'Déconnexion',
        'admin'              => 'Admin',
        'language'           => 'Langue',

        // --- POS ---
        'search_products'    => '🔍 Rechercher des produits...',
        'cart'               => 'Panier',
        'clear'              => 'Vider',
        'add_customer'       => '👤 Ajouter un client (optionnel)',
        'cart_empty'         => 'Le panier est vide',
        'click_to_add'       => 'Cliquez sur les produits pour ajouter',
        'subtotal'           => 'Sous-total',
        'tax'                => 'Taxe',
        'total'              => 'TOTAL',
        'cash'               => 'Espèces',
        'card'               => 'Carte',
        'checkout'           => 'Paiement',
        'select_customer'    => 'Sélectionner un client',
        'complete_sale'      => 'Finaliser la vente',
        'total_to_pay'       => 'Total à payer :',
        'cash_received'      => 'Montant reçu',
        'change'             => 'Monnaie',
        'sale_complete'      => 'Vente terminée !',
        'invoice'            => 'Facture',
        'new_sale'           => 'Nouvelle vente',
        'no_products'        => 'Aucun produit trouvé',
        'no_customers'       => 'Aucun client trouvé',
        'no_more_stock'      => '⚠️ Plus de stock',
        'stock_limit'        => '⚠️ Limite de stock atteinte',
        'clear_cart_confirm' => 'Vider le panier ?',
        'insufficient'       => '⚠️ Montant insuffisant',
        'server_error'       => '❌ Erreur serveur',
        'network_error'      => '❌ Erreur réseau',
        'sale_failed'        => 'Échec de la vente',
        'out'                => 'ÉPUISÉ',
    ],
];

// ============================================================
// ✅ Helper - translate a key
// Usage: <?= __('save') ?> → 'Save' / 'حفظ' / 'Enregistrer'
// ============================================================
function __($key) {
    global $translations, $currentLang;
    return $translations[$currentLang][$key] 
        ?? $translations['en'][$key] 
        ?? $key;
}

// ============================================================
// ✅ Helper - get current direction (ltr/rtl)
// Usage: <?= getLangDir() ?>
// ============================================================
function getLangDir() {
    global $langMeta, $currentLang;
    return $langMeta[$currentLang]['dir'] ?? 'ltr';
}

// ============================================================
// ✅ Helper - render the language switcher button
// Usage: <?= renderLangSwitcher() ?>
// ============================================================
function renderLangSwitcher() {
    global $langMeta, $currentLang;
    $current = $langMeta[$currentLang];
    
    $html  = '<div class="lang-switcher">';
    $html .= '<button type="button" class="lang-btn" id="langToggle">';
    $html .= '<span>' . $current['flag'] . '</span>';
    $html .= '<span>' . $current['code'] . '</span>';
    $html .= '<span style="font-size:10px;opacity:0.6;">▼</span>';
    $html .= '</button>';
    $html .= '<div class="lang-dropdown" id="langDropdown">';
    
    foreach ($langMeta as $code => $meta) {
        $active = ($code === $currentLang) ? ' active' : '';
        $check  = ($code === $currentLang) ? '✓' : '';
        $url    = '?set_lang=' . $code;
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

// ============================================================
// ✅ Helper - output translations as JS object (for dynamic JS)
// Usage: <?= renderLangJS() ?>
// Then in JS: LANG.cart_empty, LANG.no_more_stock, etc.
// ============================================================
function renderLangJS() {
    global $translations, $currentLang;
    $json = json_encode($translations[$currentLang], JSON_UNESCAPED_UNICODE);
    return '<script>var LANG = ' . $json . ';</script>';
}
?>
