<?php
declare(strict_types=1);
/**
 * Plugin: Category Tab Dropdowns
 * Plugin: https://www.zen-cart.com/downloads.php?do=file&id=1003
 * GitHub: https://github.com/torvista/Zen_Cart-Category_Tab_Dropdowns
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: tpl_modules_categories_tabs.php 2024-03-24 torvista

 * Module Template - categories_tabs
 *
 * Template stub used to display categories-tabs output
 *
 * @copyright Copyright 2003-2022 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: DrByte 2020 Jul 10 Modified in v1.5.8-alpha $
 */

  include(DIR_WS_MODULES . zen_get_module_directory(FILENAME_CATEGORIES_TABS));
?>
<?php if (CATEGORIES_TABS_STATUS == '1' && sizeof($links_list) >= 1) { ?>
<div id="navCatTabsWrapper">
<div id="navCatTabsDropdown">
<ul>
<?php
	$categories_tab_query = "SELECT c.categories_id, cd.categories_name FROM ".TABLE_CATEGORIES." c, ".TABLE_CATEGORIES_DESCRIPTION . " cd WHERE c.categories_id=cd.categories_id AND c.parent_id= '0' AND cd.language_id='" . (int)$_SESSION['languages_id'] . "' AND c.categories_status='1' ORDER BY c.sort_order, cd.categories_name;";
	$categories_tab = $db->Execute($categories_tab_query);

	while (!$categories_tab->EOF)
	{
	// currently selected category
		echo '<li>';
		echo '<a class="category-top" href="'.zen_href_link(FILENAME_DEFAULT,'cPath=' . (int)$categories_tab->fields['categories_id']).'">';
		if($cPath === $categories_tab->fields['categories_id']) {
            echo '<span class="category-subs-selected">' . $categories_tab->fields['categories_name'] . '</span>';
        } else {
            echo $categories_tab->fields['categories_name'];
        }
		echo '</a>';
		$subcategories_tab_query="SELECT c.categories_id, cd.categories_name FROM ".TABLE_CATEGORIES." c, ".TABLE_CATEGORIES_DESCRIPTION . " cd WHERE c.categories_id=cd.categories_id AND c.parent_id= '".(int)$categories_tab->fields['categories_id']."' AND cd.language_id='" . (int)$_SESSION['languages_id'] . "' AND c.categories_status='1' ORDER BY c.sort_order, cd.categories_name;";
		$subcategories_tab=$db->Execute($subcategories_tab_query);
		if($subcategories_tab->RecordCount()>0)
		{
			echo '<ul>';
			while (!$subcategories_tab->EOF)
			{
				$cPath_new = "cPath=".zen_get_generated_category_path_rev($subcategories_tab->fields['categories_id']);
				echo '<li>'.'<a href="' . zen_href_link(FILENAME_DEFAULT, $cPath_new) . '">'.$subcategories_tab->fields['categories_name'].'</a></li>';
				$subcategories_tab->MoveNext();
			}
			echo '</ul>';
		}
		$products_tab_query="SELECT p.`products_id`, pd.`products_name`, pd.`language_id` FROM ".TABLE_PRODUCTS." p, ".TABLE_PRODUCTS_DESCRIPTION." pd WHERE p.`master_categories_id`='".(int)$categories_tab->fields['categories_id']."' AND p.`products_id`=pd.`products_id` AND p.products_status='1' AND pd.`language_id`='".(int)$_SESSION['languages_id']."' ORDER BY p.`products_sort_order`;";
		$products_tab=$db->Execute($products_tab_query);
		if($products_tab->RecordCount()>0)
		{
			echo '<ul>';
			while (!$products_tab->EOF)
			{
				$cPath_new=zen_get_path($categories_tab->fields['categories_id']);
				$cPath_new=str_replace('=0_', '=', $cPath_new);
				echo '<li>'.'<a href="'.zen_href_link(zen_get_info_page($products_tab->fields['products_id']),$cPath_new. '&products_id=' . $products_tab->fields['products_id']) . '">'.$products_tab->fields['products_name'].'</a></li>';
				$products_tab->MoveNext();
			}
			echo '</ul>';
		}
		echo '</li>';
		$categories_tab->MoveNext();
	}
	echo '</ul>';
	echo '</div>';
	echo '</div>';
}
?>
