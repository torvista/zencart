<?php //Plugin Category Tab Dropdowns

declare(strict_types=1);
define('CATEGORIES_TABS_DROPDOWNS', 'true');

/**
 * categories_tabs.php module
 *
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte 2020 Dec 28 Modified in v1.5.8-alpha $
 */
if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

$order_by = " order by c.sort_order, cd.categories_name ";

$sql = "SELECT c.sort_order, c.categories_id, cd.categories_name
        FROM " . TABLE_CATEGORIES . " c
        LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd ON (c.categories_id = cd.categories_id AND cd.language_id = " . (int)$_SESSION['languages_id'] . ")
        WHERE c.parent_id= " . (int)TOPMOST_CATEGORY_PARENT_ID . "
        AND c.categories_status=1 " .
        $order_by;
$categories_tab = $db->Execute($sql);

$links_list = [];
foreach ($categories_tab as $category_top) {

  // identify currently selected category
  if ($cPath === $category_top['categories_id']) {
    $new_style = 'category-top';
    $categories_tab_current = '<span class="category-subs-selected">' . $category_top['categories_name'] . '</span>';
  } else {
    $new_style = 'category-top';
    $categories_tab_current = $category_top['categories_name'];
  }

  // create link to top level category
  $links_list[$category_top['categories_id']]['top'] = '<a class="' . $new_style . '" href="' . zen_href_link(FILENAME_DEFAULT, 'cPath=' . (int)$category_top['categories_id']) . '">' . $categories_tab_current . '</a> ';

  if (CATEGORIES_TABS_DROPDOWNS === 'true') {
      $subcategories_tab_query= 'SELECT c.categories_id, cd.categories_name FROM ' . TABLE_CATEGORIES . ' c, ' . TABLE_CATEGORIES_DESCRIPTION . ' cd
                                  WHERE c.categories_id=cd.categories_id
                                  AND c.parent_id= ' . (int)$category_top['categories_id'] . '
                                  AND cd.language_id=' . (int)$_SESSION['languages_id'] . '
                                  AND c.categories_status = 1
                                  ORDER BY c.sort_order, cd.categories_name';
      $subcategories_tab = $db->Execute($subcategories_tab_query);

      if (!$subcategories_tab->EOF)
      {
          foreach ($subcategories_tab as $subcategory)
          {
              $cPath_new = 'cPath=' . zen_get_generated_category_path_rev($subcategory['categories_id']);
              $links_list[$category_top['categories_id']][$subcategory['categories_id']] = '<a href="' . zen_href_link(FILENAME_DEFAULT, $cPath_new) . '">' . $subcategory['categories_name'].'</a>';
          }
      } else {
      $products_tab_query = 'SELECT p.products_id, pd.products_name, pd.language_id FROM ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd
                              WHERE p.master_categories_id =' . (int)$category_top['categories_id'] . '
                              AND p.products_id = pd.products_id
                              AND p.products_status = 1
                              AND pd.language_id=' . (int)$_SESSION['languages_id'] . '
                              ORDER BY p.products_sort_order, pd.products_name';
      $products_tab = $db->Execute($products_tab_query);

      if(!$products_tab->EOF)
      {
          foreach ($products_tab as $product)
          {
              $cPath_new = zen_get_path($category_top['categories_id']);
              $cPath_new = str_replace('=0_', '=', $cPath_new); //TODO
              $links_list[$category_top['categories_id']][$product['products_id']] = '<a href="'.zen_href_link(zen_get_info_page($product['products_id']), $cPath_new . '&products_id=' . $product['products_id']) . '">' . $product['products_name'] . '</a>';
          }
      }
      }
  }
}
//mv_printVar($links_list);
