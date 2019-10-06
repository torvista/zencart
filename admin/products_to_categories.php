<?php //steve
//PR waiting: for extra closing div removed,  removed <body onload="init();"> duplicated in comments, removed extra div
//new 2019 09 PR not submitted
//merge in Conor code for displaying linked categories as full paths, alpha sorted, All/None selectable, filter by subcategories dropdown
//merge in Conor code for Global Tool copy linked categories from one product to another
//simplified layout and code structure: easier to see blocks when all collapsed
//removed product price info etc. show after product select list: it was ugly/out of place and all duplicated in the infobox anyway
//added onsubmit for product select: no risk (noscript button provided)
//removed onsubmit for master category change...too easy/risky
//when unlinking categories: if product is unlinked from the displayed category, display redirects to show product in its master category
//if invalid category (<=0) found in linking array, skip with error message instead of die
//infoBox "Select another product by id": removed references to current product as irrelevant
//language constants: multiple changes of texts for better explanation, changed structure and names of defines to be more logical
//Product to Category links: removed multiple treatment of master category. Passed master category from form, do not delete from P2C table and so do not re-insert into P2C table.
//Product to Category links: removed category ID column for better use of space, now shown on hover.
//SQL added LIMIT 1 where possible
//added parameters to htmlspecialchars
//replace whiles with foreach
//review case: copy linked categories
//simplified SQL for new_cat
//review of Global Tools: removal of linked products
//review of Global Tools: reset master categories
$debug_p2c = true;
$debug_class = ' class="alert-danger"';
if (!function_exists('printArray')) {
    function printArray($a, $t = 'pre')
    {
        echo "<$t>" . print_r($a, 1) . "</$t>";
    }
}
/**steve this stuff for phpstorm inspections
 * @var $messageStack
 */
///////////////////////////////////////////////////////////////////////////////////////////////
/**
 * @package admin
 * @copyright Copyright 2003-2019 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: torvista 2019 June 16 Modified in v1.5.7 $
 */
require('includes/application_top.php');

$_GET['products_filter'] = $products_filter = (int)(
(isset($_POST['products_filter']) ? (int)$_POST['products_filter'] :
    (isset($_GET['products_filter']) && $_GET['products_filter'] > 0 ? (int)$_GET['products_filter'] : '0')));

$_GET['current_category_id'] = $current_category_id = (int)(isset($_GET['current_category_id']) ? $_GET['current_category_id'] : $current_category_id);

// Verify that at least one product exists
$chk_products = $db->Execute("SELECT *
                              FROM " . TABLE_PRODUCTS . "
                              LIMIT 1");
if ($chk_products->RecordCount() < 1) {
    $messageStack->add_session(ERROR_DEFINE_PRODUCTS, 'caution');
    zen_redirect(zen_href_link(FILENAME_CATEGORY_PRODUCT_LISTING));
}

// Verify that product has a master_categories_id
if ($products_filter > 0) {
    $source_product_details = '<strong>' . $products_filter . ' "' . zen_get_products_name($products_filter,
            (int)$_SESSION['languages_id']) . '" (' . zen_get_products_model($products_filter) . ')</strong>'; // Used for various messageStack
    $chk_products = $db->Execute("SELECT master_categories_id
                              FROM " . TABLE_PRODUCTS . "
                              WHERE products_id = " . $products_filter . " LIMIT 1");
    if (!$chk_products->EOF && $chk_products->fields['master_categories_id'] < 1) {
        $messageStack->add(sprintf(ERROR_DEFINE_PRODUCTS_MASTER_CATEGORIES_ID, $source_product_details), 'error');
//    zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
    }
}

require(DIR_WS_CLASSES . 'currencies.php');
$currencies = new currencies();

$languages = zen_get_languages();

$action = (isset($_GET['action']) ? $_GET['action'] : '');

if ($action == 'new_cat') {//this form action is from products_previous_next_display.php when a new category is selected
     $new_product_query = $db->Execute("SELECT ptc.*
                                     FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc
                                     LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON ptc.products_id = pd.products_id
                                       AND pd.language_id = " . (int)$_SESSION['languages_id'] . "
                                     WHERE ptc.categories_id = " . $current_category_id . "
                                     ORDER BY pd.products_name"); // Order By determines which product is pre-selected in the list when a new category is viewed
    $products_filter = (!$new_product_query->EOF) ? $new_product_query->fields['products_id'] : ''; // Empty if category has no products/has subcategories
    zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
}

// set categories and products if not set
if ($products_filter == '' && !empty($current_category_id)) { // when prev-next has been changed to a category without products/with subcategories
    $new_product_query = $db->Execute("SELECT ptc.products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc WHERE ptc.categories_id = " . $current_category_id . " LIMIT 1");
    $products_filter = (!$new_product_query->EOF) ? $new_product_query->fields['products_id'] : ''; // Empty if category has no products/has subcategories
    if ($products_filter != '') {
        $messageStack->add_session(WARNING_PRODUCTS_LINK_TO_CATEGORY_REMOVED, 'caution');
        zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
    }

} elseif ($products_filter == '' && empty($current_category_id)) {// on first entry into page from Admin menu
        $reset_categories_id = zen_get_category_tree('', '', '0', '', '', true);
        $current_category_id = $reset_categories_id[0]['id'];
        $new_product_query = $db->Execute("SELECT ptc.products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " ptc WHERE ptc.categories_id = " . $current_category_id . " LIMIT 1");
        $products_filter = (!$new_product_query->EOF) ? $new_product_query->fields['products_id'] : '';// Empty if category has no products/has subcategories
        $_GET['products_filter'] = $products_filter;
}

require(DIR_WS_MODULES . FILENAME_PREV_NEXT);

/**
 * validate the user-entered categories from the Global Tools
 */
function zen_validate_categories($ref_category_id, $target_category_id = '', $reset_master_category = false)
{
    global $db, $messageStack;

    $categories_valid = true;
    if ($ref_category_id == '' || zen_get_categories_status($ref_category_id) == '') {//REF does not exist
        $categories_valid = false;
        $messageStack->add_session(sprintf(WARNING_CATEGORY_SOURCE_NOT_EXIST, $ref_category_id), 'warning');
    }
    if (!$reset_master_category && ($target_category_id == '' || zen_get_categories_status($target_category_id) == '')) {//TARGET does not exist
        $categories_valid = false;
        $messageStack->add_session(sprintf(WARNING_CATEGORY_TARGET_NOT_EXIST, $target_category_id), 'warning');
    }
    if (!$reset_master_category && ($categories_valid && $ref_category_id == $target_category_id)) {//category IDs are the same
        $categories_valid = false;
        $messageStack->add_session(sprintf(WARNING_CATEGORY_IDS_DUPLICATED, $ref_category_id), 'warning');
    }

    if ($categories_valid) {
        $check_category_from = $db->Execute("SELECT products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE categories_id = " . $ref_category_id . " LIMIT 1");

        // check if REF has any products
        if ($check_category_from->RecordCount() < 1) {//there are no products in the FROM category: invalid
            $categories_valid = false;
            $messageStack->add_session(sprintf(WARNING_CATEGORY_NO_PRODUCTS, $ref_category_id), 'warning');
        }
        // check that TARGET has no subcategories
        if (!$reset_master_category && zen_childs_in_category_count($target_category_id) > 0) {//subcategories exist in the TO category: invalid
            $categories_valid = false;
            $messageStack->add_session(sprintf(WARNING_CATEGORY_SUBCATEGORIES, $target_category_id), 'warning');
        }
    }
    return $categories_valid;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
// BEGIN CEON MODIFICATIONS 1.2.0 1 of 28

// Default to top category
if (isset($_POST['target_category_id'])) {
    $target_category_id = $_POST['target_category_id'];
} elseif (isset($_GET['target_category_id'])) {
        $target_category_id = $_GET['target_category_id'];
    } else {
        $target_category_id = '0';
    }

// {{{ CeonCategoriesInfo()

/**
 * Class is simply used to minimise the number of changes required to the standard Zen Cart code by
 * mimicking the structure of a Zen Cart database results set.
 *
 * @access  public
 * @return  none
 */
class CeonCategoriesInfo
{
    var $fields = array();

    function __construct()
    {

    }
}

// }}}


// {{{ ceonGetCategoriesInfo()

/**
 * Updates a global variable, $categories_info, with a list of all the categories and subcategories
 * of the specified parent category. Code is organised so that the list is in ascending alphabetical
 * order, for the entire path of the category (i.e. not simply ordered by individual subcategory
 * names).
 *
 * @param integer $parent_id The ID of the parent category.
 * @param string $category_path_string The full path of the names of all the parent
 *                                            categories being included in the path for the
 *                                            (sub)categories info being generated.
 * @return void
 */
function ceonGetCategoriesInfo($parent_id, $category_path_string = '')
{
    global $db, $categories_info;

    $categories_sql = "SELECT cd.categories_id, cd.categories_name FROM
      " . TABLE_CATEGORIES . " c
    LEFT JOIN
      " . TABLE_CATEGORIES_DESCRIPTION . " cd
    ON
      c.categories_id = cd.categories_id
    WHERE
      c.parent_id = " . (int)$parent_id . "
    AND
      cd.language_id = " . (int )$_SESSION['languages_id'] . "
    ORDER BY
      cd.categories_name";

    $categories_result = $db->Execute($categories_sql);

    foreach ($categories_result as $category_result) {
        $category_id = $category_result['categories_id'];
        $category_name = (strlen($category_path_string) > 0 ? $category_path_string . ' > ' : '') .
            $category_result['categories_name'];

        // Does this category have subcategories?
        $sub_categories_check_sql = "SELECT c.categories_id FROM " . TABLE_CATEGORIES . " c WHERE c.parent_id = " . (int)$category_id;
        $sub_categories_check_result = $db->Execute($sub_categories_check_sql);

        if (!$sub_categories_check_result->EOF) {
            // category has subcategories, get the info for them
            ceonGetCategoriesInfo($category_id, $category_name);
        } else {
            $categories_info[] = array(
                'categories_id' => $category_id,
                'categories_name' => $category_name
            );
        }
    }
}

// }}}

// {{{ ceonGetTargetCategoryList()

/**
 * Builds a list of all the subcategories of a specified parent category which have subcategories
 * themselves.
 *
 * @param integer $parent_id The ID of the parent category.
 * @param string $spacing HTML to be prepended to the names of the categories for the
 *                                 specified parent category. Aids a hierarchical display of
 *                                 categories when information is used in a select gadget.
 * @param array $category_tree_array The array of category information being generated.
 *                                           Passed in function parameters so that it can be
 *                                           appended to when used recursively.
 * @return  none
 */
function ceonGetTargetCategoryList($parent_id = '0', $spacing = '', $category_tree_array = '')
{
    global $db;

    if (!is_array($category_tree_array)) {
        $category_tree_array = array();
    }

    $categories = $db->Execute("SELECT c.categories_id, cd.categories_name, c.parent_id
                              FROM " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd
                              WHERE c.categories_id = cd.categories_id
                              AND cd.language_id = " . (int)$_SESSION['languages_id'] . "
                              AND c.parent_id = " . (int)$parent_id . "
                              ORDER BY c.sort_order, cd.categories_name");

    foreach ($categories as $category) {
        // Only include categories which have subcategories?
        $sub_categories_check_sql = "SELECT c.categories_id FROM " . TABLE_CATEGORIES . " c WHERE c.parent_id = " . (int)$category['categories_id'];
        $sub_categories_check_result = $db->Execute($sub_categories_check_sql);

        if (!$sub_categories_check_result->EOF) {
            $category_tree_array[] = array(
                'id' => $category['categories_id'],
                'text' => $spacing . $category['categories_name']
            );

            $category_tree_array = ceonGetTargetCategoryList($category['categories_id'],
                $spacing . '&nbsp;&nbsp;&nbsp;', $category_tree_array);
        }
    }
    return $category_tree_array;
}

// }}}

// {{{ ceonGetTargetCategoryProductList()

/**
 * Builds a list of all the subcategories and products of a specified parent category.
 *
 * @param integer $parent_id The ID of the parent category.
 * @param string $spacing HTML to be prepended to the names of the categories/products for
 *                                 the specified parent category. Aids a hierarchical display of
 *                                 categories/products when information is used in a select gadget.
 * @param array $category_product_tree_array The array of categories and products being
 *                                                   generated. Passed in function parameters so
 *                                                   that it can be appended to when used
 *                                                   recursively.
 * @return  none
 */
function ceonGetTargetCategoryProductList($parent_id = '0', $spacing = '', $category_product_tree_array = '')
{
    global $db, $products_filter;

    if (!is_array($category_product_tree_array)) {
        $category_product_tree_array = array();
    }

    $categories = $db->Execute("SELECT cd.categories_id, cd.categories_name
    FROM
      " . TABLE_CATEGORIES . " c,
      " . TABLE_CATEGORIES_DESCRIPTION . " cd
    WHERE
      c.categories_id = cd.categories_id
    AND
      cd.language_id = " . (int)$_SESSION['languages_id'] . "
    AND
      c.parent_id = " . (int)$parent_id . "
    ORDER BY
      c.sort_order,
      cd.categories_name");

    foreach ($categories as $category) {
        // Get all subcategories for the current category
        $sub_categories_sql = "SELECT c.categories_id FROM " . TABLE_CATEGORIES . " c WHERE c.parent_id = " . (int)$category['categories_id'];
        $sub_categories_result = $db->Execute($sub_categories_sql);

        if (!$sub_categories_result->EOF) {
            $category_product_tree_array = ceonGetTargetCategoryProductList(
                $category['categories_id'], $spacing . $category['categories_name'] .
                ' - ', $category_product_tree_array);
        }

        $products_sql = "
      SELECT
        pd.products_id,
        pd.products_name
      FROM
        " . TABLE_PRODUCTS . " p
      LEFT JOIN
        " . TABLE_PRODUCTS_DESCRIPTION . " pd
      ON
        p.products_id = pd.products_id
      WHERE
        p.master_categories_id = " . (int)$category['categories_id'] . "
      AND
        pd.language_id = " . (int)$_SESSION['languages_id'] . "
      ORDER BY
        pd.products_name";

        $products_result = $db->Execute($products_sql);

        //while (!$products_result->EOF) {
        foreach ($products_result as $product_result) {
            if ($product_result['products_id'] != $products_filter) {
                $category_product_tree_array[] = array(
                    'id' => $product_result['products_id'],
                    'text' => $spacing . $category['categories_name'] . ' : ' .
                        $product_result['products_name'] . ' #' . $product_result['products_id']
                );
            }
        }
    }
    return $category_product_tree_array;
}

// }}}

// END CEON MODIFICATIONS 1.2.0 1 of 28
/////////////////////////////////////////////////////////////////////////////////////////////////////
if (zen_not_null($action)) {
    switch ($action) {

        // Global Tools: Copy products in Source category as linked products in Target category
        case 'copy_products_as_linked':
            $category_id_source = (int)$_POST['category_id_source'];
            $category_id_target = (int)$_POST['category_id_target'];

            if (!zen_validate_categories($category_id_source, $category_id_target)) {
                zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
            }

            // if either category was invalid nothing processes below

            // get products from source category
            $products_to_categories_links_source = $db->Execute("SELECT products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE categories_id = " . $category_id_source);
            $add_links_array = array();
            foreach ($products_to_categories_links_source as $item) {
                $add_links_array[] = array('products_id' => $item['products_id']);
            }

            // get products from target category
            $products_to_categories_links_target = $db->Execute("SELECT products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE categories_id = " . $category_id_target);
            $current_target_links_array = array();
            foreach ($products_to_categories_links_target as $item) {
                $current_target_links_array[] = array('products_id' => $item['products_id']);
            }

            // check for elements in $current_target_links_array that are already in $add_links_array
            $make_links_result = array();
            for ($i = 0, $n = count($add_links_array); $i < $n; $i++) {
                $good = 'true';
                for ($j = 0, $nn = count($current_target_links_array); $j < $nn; $j++) {
                    if ($add_links_array[$i]['products_id'] == $current_target_links_array[$j]['products_id']) {
                        $good = 'false';
                        break;
                    }
                }
                // build array of new (unlinked) products to copy
                if ($good == 'true') {
                    $make_links_result[] = array('products_id' => $add_links_array[$i]['products_id']);
                }
            }
            if (count($make_links_result) == 0) {//nothing new to copy
                $messageStack->add_session(sprintf(WARNING_COPY_FROM_IN_TO_LINKED, $category_id_source, $category_id_target), 'caution');
            } else {//do the copy
                $products_copied_message = '';
                for ($i = 0, $n = count($make_links_result); $i < $n; $i++) {
                    $new_product = $make_links_result[$i]['products_id'];
                    $sql = "INSERT INTO " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) VALUES ('" . $new_product . "', '" . $category_id_target . "')";
                    $db->Execute($sql);
                    $products_copied_message .= sprintf(SUCCESS_PRODUCT_COPIED, $make_links_result[$i]['products_id'], zen_get_products_name($$make_links_result[$i]['products_id'], (int)$_SESSION['languages_id']), zen_get_products_model($make_links_result[$i]['products_id']), $category_id_target);
                }
                $products_copied_message .= sprintf(SUCCESS_COPY_LINKED, $i, $category_id_source, $category_id_target);
                $messageStack->add_session($products_copied_message, 'success');
            }
            zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
            break;

        // Global Tools: Copy Linked categories from this product to another
        case 'copy_linked_categories_to_another_product':
            $copy_categories_type = !empty($_POST['type']) && $_POST['type'] != 'replace' ? 'add' : 'replace';
            $target_product_id = (int)$_POST['target_product_id'];

            if ($target_product_id == '') {
                $messageStack->add(WARNING_COPY_LINKED_CATEGORIES_NO_TARGET, 'error');
            } else {
                $target_product_details = '<strong>' . $target_product_id . ' "' . zen_get_products_name($target_product_id,
                        (int)$_SESSION['languages_id']) . '" (' . zen_get_products_model($target_product_id) . ')</strong>'; // Used in messageStack

                // Get the master category for the source product
                $source_product_master_category_sql = "SELECT master_categories_id FROM " . TABLE_PRODUCTS . " WHERE products_id = " . $products_filter . " LIMIT 1";
                $source_product_master_category_result = $db->Execute($source_product_master_category_sql);

                // Get the master category for the target product
                $target_product_master_category_sql = "SELECT master_categories_id FROM " . TABLE_PRODUCTS . " WHERE products_id = " . $target_product_id . " LIMIT 1";
                $target_product_master_category_result = $db->Execute($target_product_master_category_sql);

                if (!$source_product_master_category_result->EOF && !$target_product_master_category_result->EOF) {
                    $source_product_master_categories_id = $source_product_master_category_result->fields['master_categories_id'];
                    $target_product_master_categories_id = $target_product_master_category_result->fields['master_categories_id'];

                    // Get the current product's linked categories
                    $product_categories_result = $db->Execute("SELECT categories_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE products_id = " . $products_filter . " 
                    AND categories_id != " . $source_product_master_categories_id . " 
                    AND categories_id != " . $target_product_master_categories_id);

                    // Get the target product's linked categories
                    $target_categories_result = $db->Execute("SELECT categories_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE products_id = " . $target_product_id . " 
                    AND categories_id !=" . $target_product_master_categories_id . " 
                    AND categories_id !=" . $source_product_master_categories_id
                    );

                    $product_categories = array();
                    foreach ($product_categories_result as $row) {
                        $product_categories[] = $row['categories_id'];
                    }
                    //echo __LINE__ . ': $product_categories)<br>';printArray($product_categories);//debug

                    $target_categories = array();
                    foreach ($target_categories_result as $row) {
                        $target_categories[] = $row['categories_id'];
                    }
                    //echo __LINE__ . ': $target_categories)<br>';printArray($target_categories);//debug

                    $target_categories_update = array();
                    switch ($copy_categories_type) {
                        case 'add':
                            foreach ($product_categories as $id) {
                                if (!in_array($id, $target_categories)) { // Include only NEW linked categories from source product
                                    $target_categories_update[] = $id;
                                }
                            }
                            break;

                        case 'replace':
                            $db->Execute("DELETE FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE products_id = " . $target_product_id . " AND categories_id != " . $target_product_master_categories_id);
                            $target_categories_update = $product_categories;
                            break;
                    }

                    if (sizeof($target_categories_update) < 1) {// No new categories to add
                        $messageStack->add(sprintf(WARNING_COPY_LINKED_CATEGORIES_NO_ADDITIONAL, $source_product_details, $target_product_details), 'warning');
                        break;
                    }

                    foreach ($target_categories_update as $target_category) {
                        $db->Execute("INSERT INTO " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) VALUES (" . $target_product_id . ", " . $target_category . ")");
                    }

                    $target_product_name_sql = "SELECT products_name FROM " . TABLE_PRODUCTS_DESCRIPTION . " WHERE products_id = '" . $target_product_id . "' AND language_id = " . (int)$_SESSION['languages_id'] . " LIMIT 1";
                    $target_product_name_result = $db->Execute($target_product_name_sql);

                    $messageStack->add(sprintf(($copy_categories_type == 'add' ? SUCCESS_LINKED_CATEGORIES_COPIED_TO_TARGET_PRODUCT_ADD : SUCCESS_LINKED_CATEGORIES_COPIED_TO_TARGET_PRODUCT_REPLACE),
                        sizeof($target_categories_update), $source_product_details, $target_product_details), 'success');

                } else { //source/target is missing a master category
                    if ($source_product_master_category_result->EOF) {
                        $messageStack->add(sprintf(ERROR_MASTER_CATEGORY_MISSING, $source_product_details));
                    }
                    if ($target_product_master_category_result->EOF) {
                        $messageStack->add(sprintf(ERROR_MASTER_CATEGORY_MISSING, $target_product_details));
                    }
                }
            }
            break;

        // Global Tools: Remove products from Target category that are linked from a Reference category
        case 'remove_linked_products':

            $category_id_reference = (int)$_POST['category_id_reference'];
            $category_id_target = (int)$_POST['category_id_target'];

            if (!zen_validate_categories($category_id_reference, $category_id_target)) {
                zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
            }
           // if either category was invalid nothing processes below

            // get products to be removed as added linked from
            $products_to_categories_reference_linked = $db->Execute("SELECT ptoc.products_id, p.master_categories_id
                                                          FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " ptoc
                                                          LEFT JOIN " . TABLE_PRODUCTS . " p ON ptoc.products_id = p.products_id
                                                          WHERE ptoc.categories_id = " . $category_id_reference);
            $reference_links_array = array();
            $master_categories_id_stop = array();
            foreach ($products_to_categories_reference_linked as $item) {
                if ($item['master_categories_id'] == $category_id_target) { // if a product to be removed has the same master category id as the target category: do NOT remove
                    $master_categories_id_stop[] = array(
                        'products_id' => $item['products_id'],
                        'master_categories_id' => $item['master_categories_id']
                    );
                }
                $reference_links_array[] = array(
                    'products_id' => $item['products_id'],
                    'master_categories_id' => $item['master_categories_id']
                );
            }

            $stop_warning_ = '';
            if (count($master_categories_id_stop) > 0) {//a product set to be unlinked is in its master category. Create message and abort unlinking.
                for ($i = 0, $n = count($master_categories_id_stop); $i < $n; $i++) {
                    $stop_warning .= sprintf(WARNING_PRODUCT_MASTER_CATEGORY_IN_TARGET, $master_categories_id_stop[$i]['products_id'], zen_get_products_name($master_categories_id_stop[$i]['products_id'], (int)$_SESSION['languages_id']), zen_get_products_model($master_categories_id_stop[$i]['products_id']), $category_id_target);
                    }
                $stop_warning .= sprintf(WARNING_REMOVE_LINKED_PRODUCTS_MASTER_CATEGORIES_ID_CONFLICT, $category_id_reference, $category_id_target);
                $messageStack->add_session($stop_warning, 'warning');
                zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $master_categories_id_stop[0]['products_id'] . '&current_category_id=' . $current_category_id));
            }

            // get products in target category
            $products_to_categories_target_linked = $db->Execute("SELECT products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE categories_id = " . $category_id_target);
            $target_links_array = array();
            foreach ($products_to_categories_target_linked as $item) {
                $target_links_array[] = array('products_id' => $item['products_id']);
            }
            
            // remove elements from $target_links_array that are in $reference_links_array
            $products_to_remove = array();
            for ($i = 0, $n = count($reference_links_array); $i < $n; $i++) {
                $good = 'false';
                for ($j = 0, $nn = count($target_links_array); $j < $nn; $j++) {
                    if ($reference_links_array[$i]['products_id'] == $target_links_array[$j]['products_id']) {
                        $good = 'true';
                        break;
                    }
                }
                // build array of products to remove
                if ($good == 'true') {
                    $products_to_remove[] = array('products_id' => $reference_links_array[$i]['products_id']);
                }
            }
            // check that there are some products to remove
            if (count($products_to_remove) == 0) {
                $messageStack->add_session(sprintf(WARNING_REMOVE_FROM_IN_TO_LINKED, $category_id_target, $category_id_reference), 'warning');
            } else {
                $products_removed_message = '';
                for ($i = 0, $n = count($products_to_remove); $i < $n; $i++) {
                    $sql = "DELETE FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE products_id = " . $products_to_remove[$i]['products_id'] . " AND categories_id = " . $category_id_target . " LIMIT 1";
                    $db->Execute($sql);
                    $products_removed_message  = sprintf(SUCCESS_REMOVED_PRODUCT, $products_to_remove[$i]['products_id'], zen_get_products_name($products_to_remove[$i]['products_id'], (int)$_SESSION['languages_id']), zen_get_products_model($products_to_remove[$i]['products_id']), $category_id_target);
                    }
                $products_removed_message .= sprintf(SUCCESS_REMOVE_LINKED_PRODUCTS, $i);
                $messageStack->add_session($products_removed_message, 'success');
            }

            zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
            break;

        // Global Tools: Reset the master_categories_id for all products in the selected category
        case 'reset_products_category_as_master':

            $category_id_as_master = (int)$_POST['category_id_as_master'];

            if (!zen_validate_categories($category_id_as_master, '', true)) {
                zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
            }
            // if either category was invalid nothing processes below

            $reset_master_categories_id = $db->Execute("SELECT p.products_id, p.master_categories_id, ptoc.categories_id
                                                  FROM " . TABLE_PRODUCTS . " p
                                                  LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " ptoc ON ptoc.products_id = p.products_id
                                                    AND ptoc.categories_id = " . $category_id_as_master . "
                                                  WHERE ptoc.categories_id = " . $category_id_as_master);

            foreach ($reset_master_categories_id as $item) {
                $db->Execute("UPDATE " . TABLE_PRODUCTS . " SET master_categories_id = " . (int)$category_id_as_master . " WHERE products_id = " . (int)$item['products_id']);
                // reset products_price_sorter for searches etc.
                zen_update_products_price_sorter($item['products_id']);
            }
            $messageStack->add_session(sprintf(SUCCESS_RESET_PRODUCTS_MASTER_CATEGORY, $category_id_as_master), 'success');
            zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
            break;

        // Change the master category id for the currently selected product
        case 'set_master_categories_id':
            $db->Execute("UPDATE " . TABLE_PRODUCTS . "
                    SET master_categories_id = " . (int)$_GET['master_category'] . "
                    WHERE products_id = " . $products_filter . " LIMIT 1");
            // reset products_price_sorter for searches etc.
            zen_update_products_price_sorter($products_filter);

            zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
            break;

        // Choose a product to display
        case 'set_products_filter':
            zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $_GET['products_filter'] . '&current_category_id=' . $_POST['current_category_id']));
            break;

        // Product to multiple category links: Set the root category from which to display the subcategories for selection
        case 'set_target_category':
            $target_category_id = (int)$_POST['target_category_id'];
            zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES,
                'products_filter=' . $products_filter . '&amp;current_category_id=' . $current_category_id . '&amp;target_category_id=' . $target_category_id));
            break;

        // Product to multiple category links: Update the product to multiple-categories links
        case 'update_product':
            if (!isset($_POST['categories_add'])) {//no linked categories are selected
                $_POST['categories_add'] = array();
            }
            //$current_master_categories_id = $_POST['current_master_categories_id'] ?? $master_category_id_result = $db->Execute("SELECT master_categories_id FROM " . TABLE_PRODUCTS . " WHERE products_id = '" . $products_filter . "'");
            //$current_master_categories_id = $master_category_id_result->fields['master_categories_id'];;

            if (!empty($_POST['current_master_categories_id'])) {
                $current_master_categories_id = $_POST['current_master_categories_id'];
            } else {
                $master_category_id_result = $db->Execute("SELECT master_categories_id FROM " . TABLE_PRODUCTS . " WHERE products_id = " . $products_filter . " LIMIT 1");
                $current_master_categories_id = $master_category_id_result->fields['master_categories_id'];
            }
            //$zv_check_master_categories_id = ('' !== $_POST['current_master_categories_id']);//steve original: triggers php notice when not set
            //$zv_check_master_categories_id = (!empty($_POST['current_master_categories_id']));//steve
            /*if ($debug_p2c) {
                echo __LINE__ . ': $zv_check_master_categories_id=' . $zv_check_master_categories_id . '<br>';
            }*/
            //die;
            $new_categories_sort_array = array();
//steve TODO: CEON deals with not set, maybe not required in 157
// BEGIN CEON MODIFICATIONS 1.2.0 2 of 28
            //if (isset($_POST['current_master_categories_id'])) {// The currently-selected product's master category ID is one of the subcategories of the target category
// END CEON MODIFICATIONS 1.2.0 2 of 28
            //$new_categories_sort_array[] = $_POST['current_master_categories_id'];//first entry in $new_categories_sort_array
            //$new_categories_sort_array[] = $current_master_categories_id;//first entry in $new_categories_sort_array
            //$current_master_categories_id = $_POST['current_master_categories_id'];
            /* steve changed for php notice                if (!isset($_POST['categories_add'])) {//no linked categories are selected
                                $_POST['categories_add'] = array();
                            }
            */
// BEGIN CEON MODIFICATIONS 1.2.0 3 of 28
            //} else {// The currently-selected product's master category ID is NOT one of the subcategories of the target category: get it.
            // Master category wasn't in target category, need to look up information about master
            // category ID now
            //$master_category_id_result = $db->Execute("SELECT master_categories_id FROM " . TABLE_PRODUCTS . " WHERE products_id = '" . $products_filter . "'");
            // $current_master_categories_id = $master_category_id_result->fields['master_categories_id'];
            //}
// END CEON MODIFICATIONS 1.2.0 3 of 28

            // Add the selected linked subcategories to the master category
            for ($i = 0, $n = count($_POST['categories_add']); $i < $n; $i++) {
                $new_categories_sort_array[] = (int)$_POST['categories_add'][$i];
            }
// BEGIN CEON MODIFICATIONS 1.2.0 4 of 28
            /*
    // END CEON MODIFICATIONS 1.2.0 4 of 28
          // remove existing products_to_categories for current product
          $db->Execute("delete from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id='" . $products_filter . "'");
    // BEGIN CEON MODIFICATIONS 1.2.0 5 of 28
            */
            // Build the list of categories within the target category
            $categories_info = array();

            ceonGetCategoriesInfo($target_category_id);//$target_category_id is the chosen root category that contains the subcategories to link to. This populates array $categories_info
            //if ($debug_p2c) {echo __LINE__;printArray($categories_info);}
            $num_target_categories = count($categories_info);

            // Make the list of all the possible target subcategories' IDs. At the same time, check if product master category and currently-selected category are in the list of target subcategories
            $target_categories_ids = array();
            $master_category_in_target_categories_list = false;
            $current_category_in_target_categories_list = false;
            $master_category_name = $current_category_name = '';

            for ($tc_i = 0; $tc_i < $num_target_categories; $tc_i++) {
                //$target_categories_ids[] = $categories_info[$tc_i]['categories_id'];
                if ($categories_info[$tc_i]['categories_id'] == $current_master_categories_id) {
                    //$master_category_in_target_categories_list = true;
                    $master_category_name = $categories_info[$tc_i]['categories_name'];//if the master category id is in the target list, skip it
                } else {
                    $target_categories_ids[] = $categories_info[$tc_i]['categories_id'];//load the categories to unlink
                }

                if ($categories_info[$tc_i]['categories_id'] == $current_category_id) {
                    // $current_category_in_target_categories_list = true;
                    $current_category_name = $categories_info[$tc_i]['categories_name'];//steve
                }
            }
//printArray($target_categories_ids);die;
            // 1- Unlink the product from all of the target subcategories. Subsequently below, it will then be (re-)linked into the selected target categories
            $target_categories_ids_string = implode(',', $target_categories_ids);
//steve TODO better to compare and unlink only those necessary??
            $db->Execute("DELETE FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " WHERE products_id = " . $products_filter . " AND categories_id IN (" . $target_categories_ids_string . ")");

// END CEON MODIFICATIONS 1.2.0 5 of 28
            // $reset_master_categories_id = '';
// BEGIN CEON MODIFICATIONS 1.2.0 6 of 28
            // Don't reset the product master category ID if it is not one of the target categories. The master category must remain as is. Saying that.. this master checking functionality seems to
            // be permanently disabled, it is never reset!
            // if (!$master_category_in_target_categories_list) {
            //    $reset_master_categories_id = $current_master_categories_id;
            // }
// END CEON MODIFICATIONS 1.2.0 6 of 28
            // $old_master_categories_id = $current_master_categories_id;
            // add products to categories in order of master_categories_id first then others
            $verify_current_category_id = ($current_category_id == $current_master_categories_id ? true : false);//display product in same category after linking?

            for ($i = 0, $n = count($new_categories_sort_array); $i < $n; $i++) {//contains the selected linked categories
                // is current master_categories_id in the list?
                if ($new_categories_sort_array[$i] <= 0) {
                    $messageStack->add_session(sprintf(ERROR_CATEGORY_ID_INVALID, $new_categories_sort_array[$i]), 'error');
                    //die('CANNOT ADD CATEGORY:' . $new_categories_sort_array[$i] . '<br>');
                } else {
                    if ($current_category_id == $new_categories_sort_array[$i]) {//is the product still linked to the displayed category?
                        $verify_current_category_id = true;
                    }

                    $db->Execute("INSERT INTO " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) VALUES (" . $products_filter . ", " . (int)$new_categories_sort_array[$i] . ")");
                    /*
                                        if ($reset_master_categories_id == '') {
                                            $reset_master_categories_id = $new_categories_sort_array[$i];
                                        }

                                        if ($old_master_categories_id == $new_categories_sort_array[$i]) {
                                            $reset_master_categories_id = $new_categories_sort_array[$i];
                                        }*/
                }
            }

            // reset master_categories_id in products table
            /*  if ($zv_check_master_categories_id == true) {
                  // make sure master_categories_id is set to current master_categories_id
                  $db->Execute("UPDATE " . TABLE_PRODUCTS . "
                        SET master_categories_id = " . (int)$current_master_categories_id . "
                        WHERE products_id = " . $products_filter);
              } else {
                  // reset master_categories_id to current_category_id because it was unselected
                  if ($reset_master_categories_id == '') {
                      $reset_master_categories_id = $current_category_id;
                      // Ensure that product is reachable by product/category relationship.
                      $db->Execute("INSERT INTO " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id)
                          VALUES (" . $products_filter . ", " . (int)$reset_master_categories_id . ")");
                  }
                  $db->Execute("UPDATE " . TABLE_PRODUCTS . "
                        SET master_categories_id = " . (int)$reset_master_categories_id . "
                        WHERE products_id = " . $products_filter);
              }
  */
            // recalculate price based on new master_categories_id
            zen_update_products_price_sorter($products_filter);

            //          if ($zv_check_master_categories_id == true) {
            $messageStack->add_session(sprintf(SUCCESS_PRODUCT_LINKED_TO_CATEGORIES, $source_product_details), 'success');
            //        } else {
            //    $messageStack->add_session(WARNING_MASTER_CATEGORIES_ID, 'warning');
            //      }

// BEGIN CEON MODIFICATIONS 1.2.0 7 of 28
            // If current category is not an option in the target list then of course it can't be
            // deselected!
            //    if (!$current_category_in_target_categories_list) {
            //$verify_current_category_id = true;
            //  }
            /*
    // END CEON MODIFICATIONS 1.2.0 7 of 28
          // if product was removed from current categories_id stay in same category
          if (!$verify_current_category_id) {
            zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'current_category_id=' . $current_category_id));
          } else {
            zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id));
          }
    // BEGIN CEON MODIFICATIONS 1.2.0 8 of 28
            */
            // if product was removed from current categories_id stay in same category
            /*   if (!$verify_current_category_id) {
                   zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'current_category_id=' . $current_category_id . '&amp;target_category_id=' . $target_category_id));
               } else {
                   zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&amp;current_category_id=' . $current_category_id .
                       '&amp;target_category_id=' . $target_category_id));
               }*/
            //steve changed
            if (!$verify_current_category_id) {// if product was unlinked from the current categories_id, show product in it's master category
                $messageStack->add_session(sprintf(WARNING_PRODUCT_UNLINKED_FROM_CATEGORY, $current_category_name, $current_category_id), 'warning');
                zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES,
                    'products_filter=' . $products_filter . '&amp;current_category_id=' . $current_master_categories_id . '&amp;target_category_id=' . $target_category_id));
            } else {// if product continues to be linked into the current categories_id, return to that category
                zen_redirect(zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&amp;current_category_id=' . $current_category_id .
                    '&amp;target_category_id=' . $target_category_id));
            }
// END CEON MODIFICATIONS 1.2.0 8 of 28
            break;
    }
}

if ($products_filter > 0) {
    $product_to_copy = $db->Execute("SELECT p.products_id, pd.products_name, p.products_sort_order, p.products_price_sorter, p.products_model, p.master_categories_id, p.products_image
                                 FROM " . TABLE_PRODUCTS . " p,
                                      " . TABLE_PRODUCTS_DESCRIPTION . " pd
                                 WHERE p.products_id = " . $products_filter . "
                                 AND p.products_id = pd.products_id
                                 AND pd.language_id = " . (int)$_SESSION['languages_id'] . " LIMIT 1");
}
// BEGIN CEON MODIFICATIONS 1.2.0 10 of 28
/*
// END CEON MODIFICATIONS 1.2.0 10 of 28
//  $categories_query = "select distinct cd.categories_id from " . TABLE_CATEGORIES_DESCRIPTION . " cd left join " . TABLE_PRODUCTS_TO_CATEGORIES . " ptoc on cd.categories_id = ptoc.categories_id and cd.language_id = '" . (int)$_SESSION['languages_id'] . "'";

$categories_query = "SELECT DISTINCT ptoc.categories_id, cd.*
                     FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " ptoc
                     LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd ON cd.categories_id = ptoc.categories_id
                       AND cd.language_id = " . (int)$_SESSION['languages_id'] . "
                     ORDER BY cd.categories_name";
$categories_list = $db->Execute($categories_query);

// BEGIN CEON MODIFICATIONS 1.2.0 11 of 28
*/
// Build the list of categories within the target category
$categories_info = array();
ceonGetCategoriesInfo($target_category_id);

//if ($debug_p2c) {printArray($categories_info);}
// END CEON MODIFICATIONS 1.2.0 11 of 28

$products_list = $db->Execute("SELECT products_id, categories_id
                               FROM " . TABLE_PRODUCTS_TO_CATEGORIES . "
                               WHERE products_id = '" . $products_filter . "'");

?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <meta charset="<?php echo CHARSET; ?>">
    <title><?php echo TITLE; ?></title>
    <link rel="stylesheet" href="includes/stylesheet.css">
    <link rel="stylesheet" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
    <script src="includes/menu.js"></script>
    <script src="includes/general.js"></script>
    <script>
        function init() {
            cssjsmenu('navbar');
            if (document.getElementById) {
                var kill = document.getElementById('hoverJS');
                kill.disabled = true;
            }
        }
    </script>
    <style>
        select#target_product_id { /*hack to limit the width of the "Copy Linked Categories to Another Product" drop-down surrounding container, otherwise over-long option values push page layout to the right */
            width: 600px;
            text-overflow: ellipsis;
        }

        label, input[type="checkbox"] { /*override bootstrap*/
            font-weight: normal;
            padding: 0;
            margin: 0;
        }

        .TargetCategoryCheckbox:checked + .labelForCheck { /*highlight linked category checkboxes*/
            background: yellow;
        }

        .floatButton {
            -webkit-box-shadow: 0 0 6px 0 rgba(0, 0, 0, 0.8);
            -moz-box-shadow: 0 0 6px 0 rgba(0, 0, 0, 0.8);
            box-shadow: 0 0 6px 0 rgba(0, 0, 0, 0.8);
            bottom: 200px;
        }

        .floatButton span { /*product name and model in Update Categories button*/
            font-style: italic;
        }

        #infoBox {
            border: 1px solid darkgrey;
        }

        #p2c-table td {
            /*white-space: nowrap;*/
        }

        .dataTableHeadingRow {
            padding: 0 0 5px 5px;
            border: 1px black solid;
            margin-bottom: 10px;
        }

        .form-group-row div { /*to get three boxes bottom-aligned*/
            float: none;
            display: table-cell;
            vertical-align: bottom;
        }

        .form-group-row div label { /*to get three boxes bottom-aligned*/
            font-weight: bold;
            text-align: left !important;
        }

        .form-control {
            width: 100%;
        }
    </style>
</head>
<body onload="init();">
<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

<!-- body //-->
<div class="container-fluid">
    <!-- body_text //-->
    <h1><?php echo HEADING_TITLE; ?></h1>
    <?php echo zen_draw_separator('pixel_black.gif', '100%', '2'); ?>
    <!-- Product-category links block -->
    <!-- Product selection-infoBox block -->
    <div class="row">
        <!-- LEFT column block (prev/next, product select, master category) -->
        <div class="col-sm-9 col-md-9 col-lg-9">
            <h2><?php echo TEXT_HEADING_PRODUCT_SELECT; ?></h2>

            <!-- prev-cat-next navigation -->
            <div>
                <?php require(DIR_WS_MODULES . FILENAME_PREV_NEXT_DISPLAY); ?>
            </div>
            <!-- prev-cat-next navigation eof-->

            <!-- product select -->
            <?php if ($products_filter > 0) {//a product is selected ?>
                <div>
                    <?php echo zen_draw_form('set_products_filter_id', FILENAME_PRODUCTS_TO_CATEGORIES, 'action=set_products_filter', 'post', 'class="form-horizontal"') ?>
                    <?php echo zen_draw_hidden_field('products_filter', $products_filter); ?>
                    <?php echo zen_draw_hidden_field('current_category_id', $_GET['current_category_id']); ?>
                    <?php
                    $excluded_products = array();
                    //              $not_for_cart = $db->Execute("select p.products_id from " . TABLE_PRODUCTS . " p left join " . TABLE_PRODUCT_TYPES . " pt on p.products_type= pt.type_id where pt.allow_add_to_cart = 'N'");
                    //              while (!$not_for_cart->EOF) {
                    //                $excluded_products[] = $not_for_cart->fields['products_id'];
                    //                $not_for_cart->MoveNext();
                    //              }
                    ?>
                    <?php echo zen_draw_label(TEXT_PRODUCT_TO_VIEW, 'products_filter'); ?>
                    <?php //steve added onchange autosubmit behaviour ?>
                    <?php echo zen_draw_products_pull_down('products_filter', 'size="10" class="form-control" id="products_filter" onchange="this.form.submit()"', $excluded_products, true,
                        $products_filter, true, true, true); ?>
                    <noscript>
                        <br><input type="submit" value="<?php echo IMAGE_DISPLAY; ?>">
                    </noscript>
                    <?php echo '</form>'; ?>
                </div>
            <?php } ?>
            <!-- product select eof -->

            <!-- master category select -->
            <?php if ($products_filter > 0) {//a product is selected ?>
                <div class="row">
                    <h3><?php echo TEXT_MASTER_CATEGORIES_ID; ?></h3>
                    <div class="col-lg-6"><?php echo TEXT_INFO_MASTER_CATEGORY_CHANGE; ?></div>

                    <div class="col-lg-6">
                        <?php if ($product_to_copy->EOF) { //product not linked to ANY category: missing a master category ID/ID invalid ?>
                            <span class="alert" style="font-size: larger;padding:0;"><?php echo sprintf(TEXT_PRODUCTS_ID_INVALID, $products_filter); ?></span>

                        <?php } else { //show drop-down for master category re-assignment ?>
                            <div class="form-group">
                                <?php
                                echo zen_draw_form('restrict_product', FILENAME_PRODUCTS_TO_CATEGORIES, '', 'get', 'class="form-horizontal"', true);
                                echo zen_draw_hidden_field('action', 'set_master_categories_id');
                                echo zen_draw_hidden_field('products_filter', $products_filter);
                                echo zen_draw_hidden_field('current_category_id', $_GET['current_category_id']);
                                echo zen_hide_session_id();
                                zen_draw_label(zen_image(DIR_WS_IMAGES . ($product_to_copy->fields['master_categories_id'] > 0 ? 'icon_green_on.gif' : 'icon_red_on.gif'),
                                        IMAGE_ICON_LINKED) . '&nbsp;' . TEXT_MASTER_CATEGORIES_ID, 'master_category');
                                echo zen_draw_pull_down_menu('master_category', zen_get_master_categories_pulldown($products_filter), $product_to_copy->fields['master_categories_id'],
                                    'class="form-control" id="master_category"'); ?>
                                <button type="submit" class="btn btn-info"><?php echo IMAGE_UPDATE; ?></button>
                                <?php
                                if ($product_to_copy->fields['master_categories_id'] < 1) { ?>
                                    <span class="alert" style="font-size: larger;padding:0;"><?php echo sprintf(WARNING_MASTER_CATEGORIES_ID, $products_filter); ?></span>
                                <?php } ?>
                                <?php echo '</form>'; ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
            <!-- master category select eof-->
        </div>
        <!-- LEFT column block (prev/next, product select, master category) eof -->

        <!-- RIGHT column block (infoBox) -->
        <div class="col-sm-3 col-md-3 col-lg-3">
            <!-- infoBox -->
            <?php if ($products_filter > 0) {//a product is selected ?>
                <div id="infoBox" style="display:table;margin:0 auto;">
                    <?php
                    $heading = array();
                    $contents = array();

                    switch ($action) {
                        case 'edit'://select a different product by ID
                            $heading[] = array('text' => '<h4>' . TEXT_INFOBOX_HEADING_SELECT_PRODUCT . '</h4>');
                            $contents = array('form' => zen_draw_form('product_select_by_id', FILENAME_PRODUCTS_TO_CATEGORIES, '', 'post', 'class="form-horizontal"'));
                            /*if ($products_filter > 0) {
                                $contents[] = array(
                                    'text' => zen_image(DIR_WS_CATALOG_IMAGES . $product_to_copy->fields['products_image'], $product_to_copy->fields['products_name'], SMALL_IMAGE_WIDTH,
                                        SMALL_IMAGE_HEIGHT)
                                );
                            }*/
                            $contents[] = array('text' => TEXT_SET_PRODUCTS_TO_CATEGORIES_LINKS);
                            /* $contents[] = array('text' => TEXT_PRODUCTS_NAME . '<strong>' . $product_to_copy->fields['products_name'] . '</strong>');
                             $contents[] = array('text' => TEXT_PRODUCTS_MODEL . ' <strong>' . $product_to_copy->fields['products_model'] . '</strong>');*/
                            $contents[] = array(
                                'text' => zen_draw_label(TEXT_PRODUCTS_ID, 'products_filter', 'class="control-label"') . zen_draw_input_field('products_filter', $products_filter,
                                        'class="form-control"')
                            );
//      $contents[] = array('align' => 'center', 'text' => '<br />' . zen_image_submit('button_update.gif', IMAGE_UPDATE) . '&nbsp;<a href="' . zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES, 'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id) . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>' . '</form>');
                            $contents[] = array(
                                'align' => 'center',
                                'text' => '<button type="submit" class="btn btn-primary">' . IMAGE_SELECT . '</button> <a href="' . zen_href_link(FILENAME_PRODUCTS_TO_CATEGORIES,
                                        'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id) . '" class="btn btn-default" role="button">' . IMAGE_CANCEL . '</a>'
                            );
                            break;
                        default:
                            // only show if a Product is selected
                            if ($products_filter > 0) {
                                $heading[] = array('text' => '<h4>ID#' . $product_to_copy->fields['products_id'] . ' - ' . $product_to_copy->fields['products_name'] . '</h4>');
                                $contents[] = array(
                                    'text' => zen_image(DIR_WS_CATALOG_IMAGES . $product_to_copy->fields['products_image'], $product_to_copy->fields['products_name'], SMALL_IMAGE_WIDTH,
                                        SMALL_IMAGE_HEIGHT)
                                );
                                $contents[] = array('text' => TEXT_PRODUCTS_NAME . $product_to_copy->fields['products_name']);
                                $contents[] = array('text' => TEXT_PRODUCTS_MODEL . $product_to_copy->fields['products_model']);
                                $contents[] = array('text' => 'Sort Order: ' . $product_to_copy->fields['products_sort_order']);
                                $contents[] = array('text' => TEXT_PRODUCTS_PRICE . zen_get_products_display_price($products_filter));
                                $display_priced_by_attributes = zen_get_products_price_is_priced_by_attributes($products_filter);
                                $contents[] = array('text' => $display_priced_by_attributes ? '<span class="alert">' . TEXT_PRICED_BY_ATTRIBUTES . '</span>' : '');
                                $contents[] = array('text' => zen_get_products_quantity_min_units_display($products_filter, $include_break = false));

                                switch (true) {
                                    case ($product_to_copy->fields['master_categories_id'] == 0 && $products_filter > 0):
                                        $contents[] = array('text' => '<span class="alert">' . WARNING_MASTER_CATEGORIES_ID . '</span>');
                                        break;
                                    default:
                                        $contents[] = array(
                                            'align' => 'center',
                                            'text' =>
                                                '<a href="' . zen_href_link(FILENAME_PRODUCT,
                                                    'action=new_product' . '&cPath=' . zen_get_parent_category_id($products_filter) . '&pID=' . $products_filter . '&product_type=' . zen_get_products_type($products_filter)) . '" class="btn btn-info" role="button">' . IMAGE_EDIT_PRODUCT . '</a>&nbsp;' .
                                                '<a href="' . zen_href_link(FILENAME_CATEGORY_PRODUCT_LISTING,
                                                    'cPath=' . zen_get_parent_category_id($products_filter) . '&pID=' . $products_filter) . '" class="btn btn-info" role="button">' . BUTTON_CATEGORY_LISTING . '</a><br /><br />' .
                                                '<a href="' . zen_href_link(FILENAME_ATTRIBUTES_CONTROLLER,
                                                    'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id) . '" class="btn btn-info" role="button">' . IMAGE_EDIT_ATTRIBUTES . '</a>&nbsp;' .
                                                '<a href="' . zen_href_link(FILENAME_PRODUCTS_PRICE_MANAGER,
                                                    'products_filter=' . $products_filter . '&current_category_id=' . $current_category_id) . '" class="btn btn-info" role="button">' . IMAGE_PRODUCTS_PRICE_MANAGER . '</a>'
                                        );
                                        $contents[] = array('text' => zen_draw_separator('pixel_black.gif', '100%', '1'));
                                        $contents[] = array(
                                            'align' => 'center',
                                            'text' => zen_draw_form('new_products_to_categories', FILENAME_PRODUCTS_TO_CATEGORIES,
                                                    'action=edit&current_category_id=' . $current_category_id) . zen_draw_hidden_field('products_filter',
                                                    $products_filter) . '<button type="submit" class="btn btn-primary">' . BUTTON_NEW_PRODUCTS_TO_CATEGORIES . '</button></form>'
                                        );
                                        break;
                                }
                            }
                            break;
                    }

                    if ((zen_not_null($heading)) && (zen_not_null($contents))) {
                        $box = new box;
                        echo $box->infoBox($heading, $contents);
                    }
                    ?>
                </div>
            <?php } ?>
            <!-- infoBox eof -->
        </div>
        <!-- RIGHT column block (infoBox) eof -->
    </div>
    <!-- Product selection-infoBox block eof -->

    <!-- Category Links -->
    <?php if ($products_filter > 0 && $product_to_copy->fields['master_categories_id'] > 0) { //a product is selected AND it has a master category ?>
        <div class="row">
            <div class="col-lg-12">
                <h3><?php echo TEXT_HEADING_LINKED_CATEGORIES; ?></h3>
                <?php echo TEXT_INFO_PRODUCTS_TO_CATEGORIES_LINKER_INTRO; ?>
                <div class="form-group text-center">
                    <?php
                    if ($product_to_copy->fields['master_categories_id'] < 1) {
                        ?>
                        <span class="alert"><?php echo TEXT_SET_MASTER_CATEGORIES_ID; ?></span>
                        <?php
                    } else {
                        ?>
                        <!--steve disabled <button type="submit" class="btn btn-primary"><?php //echo BUTTON_UPDATE_CATEGORY_LINKS; ?></button>-->
                    <?php } ?>
                </div>
                <?php
                // BEGIN CEON MODIFICATIONS 1.2.0 13 of 28

                ?>
                <div><?php
                    echo zen_draw_form('update_target_category', FILENAME_PRODUCTS_TO_CATEGORIES,
                        'action=set_target_category&amp;products_filter=' . $products_filter . '&amp;current_category_id=' . $current_category_id, 'post');
                    $category_select_values = ceonGetTargetCategoryList(0, '&nbsp;&nbsp;&nbsp;');
                    $select_all_categories_option = array(
                        'id' => 0,
                        'text' => TEXT_TOP
                    );
                    array_unshift($category_select_values, $select_all_categories_option); ?>
                    <label><?php echo TEXT_LABEL_CATEGORY_DISPLAY_ROOT . zen_draw_pull_down_menu('target_category_id', $category_select_values, '', 'onChange="this.form.submit();"'); ?></label>
                    <?php
                    echo zen_draw_hidden_field('products_filter', $_GET['products_filter']);
                    echo zen_hide_session_id();
                    echo zen_draw_hidden_field('action', 'set_target_category'); ?>
                    <noscript>
                        <input type="submit" value="<?php echo IMAGE_DISPLAY; ?>">
                    </noscript>
                    <?php echo '</form>'; ?>
                </div>
                <div><?php
                    // Can identify checkboxes for target categories by searching for their CSS class name
                    //steve <<< is HEREDOC syntax = same as putting it in quotes, without having to escape quotes
                    $js = <<< JS_BLOCK
<script>
function ceonSelectAllNoneTargetCategories()
{
  let select_all_or_none_el = document.getElementById('select_all_or_none');
  
  let elem = document.getElementsByTagName('input');
  for (let i = 0; i < elem.length; i++) {
    let classes = elem[i].className;
    if (classes === 'TargetCategoryCheckbox') {
      elem[i].checked = select_all_or_none_el.checked;
    }
  } 
  all_target_categories_selected = false;
}
document.write('<input type="checkbox" name="select_all_or_none" id="select_all_or_none" {checked} onclick="javascript:ceonSelectAllNoneTargetCategories();" />');
document.write('&nbsp;<label for="select_all_or_none">{title}<\/label>');
</script>
JS_BLOCK;

                    $js = str_replace('{title}', addslashes(TEXT_LABEL_SELECT_ALL_OR_NONE), $js);

                    // Are all target categories currently selected? If so, checkbox should be checked
                    $all_target_categories_selected = true;

                    $selected_categories_check = null;

                    $selected_categories = array();

                    //while (!$products_list->EOF) {
                    foreach ($products_list as $product_list) {
                        $selected_categories[] = $product_list['categories_id'];
                        //$products_list->MoveNext();
                    }

                    if (count($selected_categories) == 0) {
                        $all_target_categories_selected = false;
                    } else {
                        // Assign fake value to variable used by Zen Cart in a check later
                        $selected_categories_check = 'yes, use it';

                        $num_target_categories = count($categories_info);

                        for ($cat_i = 0; $cat_i < $num_target_categories; $cat_i++) {
                            if (!in_array($categories_info[$cat_i]['categories_id'], $selected_categories)) {
                                $all_target_categories_selected = false;
                                break;
                            }
                        }
                    }

                    $js = str_replace('{checked}', ($all_target_categories_selected == true ?
                        'checked="checked"' : ''), $js);

                    echo $js;
                    ?></div>
                <?php // END CEON MODIFICATIONS 1.2.0 13 of 28 ?>
                <?php // BEGIN CEON MODIFICATIONS 1.2.0 14 of 28 ?>
                <?php echo zen_draw_form('update', FILENAME_PRODUCTS_TO_CATEGORIES,
                    'action=update_product&amp;products_filter=' . $products_filter . '&amp;current_category_id=' . $current_category_id . '&amp;target_category_id=' . $target_category_id,
                    'post');//steve moved form start from 603, validation ampersands
                zen_draw_hidden_field('current_master_categories_id', $product_to_copy->fields['master_categories_id']); //steve ?>
                <?php // END CEON MODIFICATIONS 1.2.0 14 of 28 ?>

                <table class="table-bordered" id="p2c-table">
                    <thead>
                    <?php // END CEON MODIFICATIONS 1.2.0 14 of 28
                    /*
                                      $selected_categories_check = '';
                                      while (!$products_list->EOF) {
                                        $selected_categories_check .= $products_list->fields['categories_id'];
                                        $products_list->MoveNext();
                                        if (!$products_list->EOF) {
                                          $selected_categories_check .= ',';
                                        }
                                      }
                                      $selected_categories = explode(',', $selected_categories_check);
                                      ?>
                                      <?php
                    // BEGIN CEON MODIFICATIONS 1.2.0 15 of 28
                    */
                    // END CEON MODIFICATIONS 1.2.0 15 of 28
                    $cnt_columns = 0;
                    ?>
                    <tr class="dataTableHeadingRow">
                        <?php
                        while ($cnt_columns != MAX_DISPLAY_PRODUCTS_TO_CATEGORIES_COLUMNS) {
                            $cnt_columns++;
                            ?>
                            <!--<th class="dataTableHeadingContent text-right"><?php //echo TEXT_INFO_ID; ?> </th>-->
                            <th class="dataTableHeadingContent">&nbsp;&nbsp;<?php echo TEXT_CATEGORIES_NAME; ?></th>
                            <?php
                        }
                        ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $cnt_columns = 0;
                    // BEGIN CEON MODIFICATIONS 1.2.0 16 of 28
                    /*
                    // END CEON MODIFICATIONS 1.2.0 16 of 28
                                      while (!$categories_list->EOF) {
                    // BEGIN CEON MODIFICATIONS 1.2.0 17 of 28
                    */
                    $num_target_categories = count($categories_info);
                    //if ($debug_p2c) {printArray($categories_info);}
                    for ($cat_i = 0; $cat_i < $num_target_categories; $cat_i++) {

                        // Create an object and populate it with the properties expected by the script (an array with
                        // the category's ID and name stored in a "fields" property)
                        $categories_list = new CeonCategoriesInfo();

                        $categories_list->fields = $categories_info[$cat_i];
//if ($debug_p2c) {echo __LINE__;printArray($categories_list->fields);}
// END CEON MODIFICATIONS 1.2.0 17 of 28
                        $cnt_columns++;
                        if (zen_not_null($selected_categories_check)) {
                            $selected = in_array($categories_list->fields['categories_id'], $selected_categories);
                        } else {
                            $selected = false;
                        }
// BEGIN CEON MODIFICATIONS 1.2.0 18 of 28
                        /*
                        // END CEON MODIFICATIONS 1.2.0 18 of 28
                                            $zc_categories_checkbox = zen_draw_checkbox_field('categories_add[]', $categories_list->fields['categories_id'], $selected);
                        // BEGIN CEON MODIFICATIONS 1.2.0 19 of 28
                        */
                        // Need to add a class to the checkbox so that it can be identified as a target category
                        // checkbox, for the purposes of selecting all/none at once
                        $zc_categories_checkbox = zen_draw_checkbox_field('categories_add[]',
                            $categories_list->fields['categories_id'], $selected, '', 'class="TargetCategoryCheckbox"');

// END CEON MODIFICATIONS 1.2.0 19 of 28
                        if ($cnt_columns == 1) {
                            ?>
                            <tr class="dataTableRow">
                            <?php
                        }
                        ?>
                        <?php //// BEGIN CEON MODIFICATIONS 1.2.0 19 of 28 EXTRA FOR 157 ?>
                        <!--<td class="dataTableContent text-right"><?php //echo $categories_list->fields['categories_id']; ?></td>-->
                        <?php //// END CEON MODIFICATIONS 1.2.0 19 of 28 EXTRA FOR 157 ?>
                        <?php
                        if ($product_to_copy->fields['master_categories_id'] == $categories_list->fields['categories_id']) {
// BEGIN CEON MODIFICATIONS 1.2.0 20 of 28
                            /*
                            // END CEON MODIFICATIONS 1.2.0 20 of 28
                                                  ?>
                                                  <td class="dataTableContent">&nbsp;<?php echo zen_image(DIR_WS_IMAGES . 'icon_green_on.gif', IMAGE_ICON_LINKED); ?>&nbsp;<?php echo $categories_list->fields['categories_name'] . zen_draw_hidden_field('current_master_categories_id', $categories_list->fields['categories_id']); ?></td>
                            // BEGIN CEON MODIFICATIONS 1.2.0 21 of 28
                            */
                            echo '  <td class="dataTableContent" title="' . TEXT_VALID_CATEGORIES_ID . ': ' . $categories_list->fields['categories_id'] . '">' . zen_image(DIR_WS_IMAGES . 'icon_green_on.gif',
                                    TEXT_MASTER_CATEGORIES_ID . $product_to_copy->fields['master_categories_id']) . '&nbsp;' . htmlspecialchars($categories_list->fields['categories_name'], ENT_COMPAT,
                                    CHARSET) . '</td>' . "\n";
// END CEON MODIFICATIONS 1.2.0 21 of 28

                        } else {
// BEGIN CEON MODIFICATIONS 1.2.0 22 of 28
                            /*
                            // END CEON MODIFICATIONS 1.2.0 22 of 28
                                                  ?>
                                                  <td class="dataTableContent"><?php echo ($selected ? '<strong>' : '') . $zc_categories_checkbox . '&nbsp;' . $categories_list->fields['categories_name'] . ($selected ? '</strong>' : ''); ?></td>
                                                  <?php
                            // BEGIN CEON MODIFICATIONS 1.2.0 23 of 28
                            */
                            echo '  <td class="dataTableContent">' . $zc_categories_checkbox . ' <label class="labelForCheck" title="' . TEXT_VALID_CATEGORIES_ID . ': ' . $categories_list->fields['categories_id'] . '">' . htmlspecialchars($categories_list->fields['categories_name'],
                                    ENT_COMPAT, CHARSET) . '</label></td>' . "\n";
// END CEON MODIFICATIONS 1.2.0 23 of 28
                        }
// BEGIN CEON MODIFICATIONS 1.2.0 24 of 28
                        /*
                        // END CEON MODIFICATIONS 1.2.0 24 of 28
                                            $categories_list->MoveNext();
                                            if ($cnt_columns == MAX_DISPLAY_PRODUCTS_TO_CATEGORIES_COLUMNS || $categories_list->EOF) {
                                              if ($categories_list->EOF && $cnt_columns != MAX_DISPLAY_PRODUCTS_TO_CATEGORIES_COLUMNS) {
                        // BEGIN CEON MODIFICATIONS 1.2.0 25 of 28
                        */
                        if ($cnt_columns == MAX_DISPLAY_PRODUCTS_TO_CATEGORIES_COLUMNS ||
                            $cat_i == ($num_target_categories - 1)) {
                            if ($cat_i == ($num_target_categories - 1) &&
                                $cnt_columns != MAX_DISPLAY_PRODUCTS_TO_CATEGORIES_COLUMNS) {
// END CEON MODIFICATIONS 1.2.0 25 of 28
                                while ($cnt_columns < MAX_DISPLAY_PRODUCTS_TO_CATEGORIES_COLUMNS) {
                                    $cnt_columns++;
                                    ?>
                                    <td class="dataTableContent">&nbsp;</td>
                                    <!--<td class="dataTableContent">&nbsp;</td>-->
                                    <?php
                                }
                            }
                            ?>
                            </tr>
                            <?php
                            $cnt_columns = 0;
                        }
                    }
                    ?>
                    </tbody>
                </table>
                <div class="form-group text-center">
                    <button type="submit" class="btn btn-primary floatButton"
                            title="<?php echo BUTTON_UPDATE_CATEGORY_LINKS . " - " . $product_to_copy->fields['products_name']; ?>"><?php echo BUTTON_UPDATE_CATEGORY_LINKS . '<br><span>' . $product_to_copy->fields['products_name'] . '<br>' . $product_to_copy->fields['products_model']; ?></span></button>
                </div>
                <?php echo '</form>'; ?>
            </div>
        </div>
    <?php } ?>
    <!-- Category Links eof -->
    <!-- Product-category links block eof-->

    <div class="row"><?php echo zen_draw_separator('pixel_black.gif', '100%', '2'); ?></div>

    <!-- Global Tools -->
    <div class="col-lg-12">
        <h2><?php echo HEADER_CATEGORIES_GLOBAL_TOOLS; ?></h2>
        <div><?php echo TEXT_PRODUCTS_ID_NOT_REQUIRED; ?></div>

        <!-- Copy linked categories from one product to another -->
        <div class="row dataTableHeadingRow">
            <?php echo zen_draw_form('copy_linked_categories_to_another_product', FILENAME_PRODUCTS_TO_CATEGORIES,
                'action=copy_linked_categories_to_another_product' . '&amp;products_filter=' . $products_filter . '&amp;current_category_id=' . $current_category_id, 'post',
                'class="form-horizontal"'); ?>
            <h3><?php echo TEXT_HEADING_COPY_LINKED_CATEGORIES; ?></h3>
            <div class="form-group-row">
                <?php echo TEXT_INFO_COPY_LINKED_CATEGORIES; ?>
            </div>
            <?php // Get the list of products and build a select gadget
            $category_product_tree_array = array();
            $category_product_tree_array[] = array(
                'id' => '',
                'text' => TEXT_OPTION_LINKED_CATEGORIES
            );
            //echo '$product_to_copy->fields[\master_categories_id\]='.$product_to_copy->fields['master_categories_id'];//steve, does not work
            //$current_master_categories_id = $product_to_copy->fields['master_categories_id'];//steve
            $category_product_tree_array = ceonGetTargetCategoryProductList(0, '', $category_product_tree_array);
            ?>
            <div class="form-group-row">
                <div class="col-lg-8">
                    <?php echo zen_draw_pull_down_menu('target_product_id', $category_product_tree_array, '', 'id="target_product_id"'); ?>
                </div>
                <div class="col-lg-2">
                    <button type="submit" class="btn btn-primary" name="type" value="add"><?php echo BUTTON_COPY_LINKED_CATEGORIES_ADD; ?></button>
                </div>
                <div class="col-lg-2">
                    <button type="submit" class="btn btn-danger" name="type" value="replace"><?php echo BUTTON_COPY_LINKED_CATEGORIES_REPLACE; ?></button>
                </div>
            </div>
            <?php echo '</form>'; ?>
        </div>
        <!-- Copy linked categories from one product to another eof -->

        <!-- Copy all products from one category to another as linked products -->
        <div class="row dataTableHeadingRow">
            <?php echo zen_draw_form('linked_copy', FILENAME_PRODUCTS_TO_CATEGORIES,
                'action=copy_products_as_linked' . '&products_filter=' . $products_filter . '&current_category_id=' . $current_category_id, 'post',
                'class="form-horizontal"'); ?>
            <h3><?php echo TEXT_HEADING_COPY_ALL_PRODUCTS_TO_CATEGORY_LINKED; ?></h3>
            <div class="form-group-row">
                <?php echo TEXT_INFO_COPY_ALL_PRODUCTS_TO_CATEGORY_LINKED; ?>
            </div>
            <div class="form-group-row">
                <div class="col-lg-4">
                    <?php echo zen_draw_label(TEXT_LABEL_COPY_ALL_PRODUCTS_TO_CATEGORY_FROM_LINKED, 'category_id_source',
                            'class="control-label"') . zen_draw_input_field('category_id_source', '', 'id="category_id_source" class="form-control" step="1" min="1"', '',
                            'number'); ?>
                </div>
                <div class="col-lg-4">
                    <?php echo zen_draw_label(TEXT_LABEL_COPY_ALL_PRODUCTS_TO_CATEGORY_TO_LINKED, 'category_id_target',
                            'class="control-label"') . zen_draw_input_field('category_id_target', '', 'id="category_id_target" class="form-control" step="1" min="1"', '',
                            'number'); ?>
                </div>
                <div class="col-lg-4">
                    <button type="submit" class="btn btn-primary"><?php echo BUTTON_COPY_CATEGORY_LINKED; ?></button>
                </div>
            </div>
            <?php echo '</form>'; ?>
        </div>
        <!-- Copy all products from one category to another as linked products eof -->

        <!-- Remove products from one category that are linked to another category -->
        <div class="row dataTableHeadingRow">
            <?php echo zen_draw_form('linked_remove', FILENAME_PRODUCTS_TO_CATEGORIES,
                'action=remove_linked_products' . '&products_filter=' . $products_filter . '&current_category_id=' . $current_category_id, 'post',
                'class="form-horizontal"'); ?>
            <h3><?php echo TEXT_HEADING_REMOVE_ALL_PRODUCTS_FROM_CATEGORY_LINKED; ?></h3>
            <div class="form-group-row">
                <?php echo sprintf(TEXT_INFO_REMOVE_ALL_PRODUCTS_TO_CATEGORY_LINKED, $current_category_id); ?>
            </div>
            <div class="form-group-row">
                <div class="col-lg-4">
                    <?php echo zen_draw_label(TEXT_LABEL_REMOVE_ALL_PRODUCTS_TO_CATEGORY_FROM_LINKED, 'category_id_reference',
                            'class="control-label"') . zen_draw_input_field('category_id_reference', '', 'id="category_id_reference" class="form-control" step="1" min="1"', '',
                            'number'); ?>
                </div>
                <div class="col-lg-4">
                    <?php echo zen_draw_label(TEXT_LABEL_REMOVE_ALL_PRODUCTS_TO_CATEGORY_TO_LINKED, 'category_id_target',
                            'class="control-label"') . zen_draw_input_field('category_id_target', '', 'id="category_id_target" class="form-control" step="1" min="1"', '',
                            'number'); ?>
                </div>
                <div class="col-lg-4">
                    <button type="submit" class="btn btn-primary"><?php echo BUTTON_REMOVE_CATEGORY_LINKED; ?></button>
                </div>
            </div>
            <?php echo '</form>'; ?>
        </div>
        <!-- Remove products from one category that are linked to another category eof -->

        <!-- Reset master_categories_id for all products in the selected category -->
        <div class="row dataTableHeadingRow">
            <?php echo zen_draw_form('master_reset', FILENAME_PRODUCTS_TO_CATEGORIES,
                'action=reset_products_category_as_master' . '&products_filter=' . $products_filter . '&current_category_id=' . $current_category_id, 'post',
                'class="form-horizontal"'); ?>
            <h3><?php echo TEXT_HEADING_RESET_ALL_PRODUCTS_TO_CATEGORY_MASTER; ?></h3>
            <div class="form-group-row">
                <?php echo TEXT_INFO_RESET_ALL_PRODUCTS_TO_CATEGORY_MASTER; ?>
            </div>
            <div class="form-group-row">
                <div class="col-lg-8">
                    <?php echo zen_draw_label(TEXT_INFO_RESET_ALL_PRODUCTS_TO_CATEGORY_FROM_MASTER, 'category_id_as_master',
                            'class="control-label"') . zen_draw_input_field('category_id_as_master', '', ' id="category_id_as_master" class="form-control" step="1" min="1"', '',
                            'number'); ?>
                </div>
                <div class="col-lg-4">
                    <button type="submit" class="btn btn-danger"><?php echo BUTTON_RESET_CATEGORY_MASTER; ?></button>
                </div>
            </div>
            <?php echo '</form>'; ?>
        </div>
        <!-- Reset master_categories_id for all products in the selected category eof -->

    </div>
    <!-- Global Tools eof -->

    <!-- body_text_eof //-->
</div>
<!-- body_eof //-->

<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->

</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
