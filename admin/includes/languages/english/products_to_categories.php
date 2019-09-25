<?php
/**
 * @package admin
 * @copyright Copyright 2003-2018 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: torvista 2019 June 16 Modified in v1.5.7 $
 */

define('HEADING_TITLE','Products to Multiple Categories Link Manager');
define('HEADING_TITLE2','Categories / Products');

define('TEXT_INFO_PRODUCTS_TO_CATEGORIES_AVAILABLE', 'Categories with Products that are Available for Linking ...');

define('TEXT_HEADING_LINKED_CATEGORIES', 'Linked Categories');
define('TEXT_HEADING_PRODUCT_SELECT', 'Select Product');
define('TEXT_LABEL_CATEGORY_DISPLAY_ROOT', 'Display the SubCategories under:');

define('TABLE_HEADING_PRODUCTS_ID', 'Prod ID');
define('TABLE_HEADING_PRODUCT', 'Product Name');
define('TABLE_HEADING_ACTION', 'Action');

define('TEXT_INFO_HEADING_EDIT_PRODUCTS_TO_CATEGORIES', 'Edit Product Links');
define('TEXT_PRODUCTS_ID', 'Product ID# ');
define('TEXT_PRODUCTS_NAME', 'Product: ');
define('TEXT_PRODUCTS_PRICE', 'Price: ');
define('BUTTON_UPDATE_CATEGORY_LINKS', 'Update Category Links');
define('BUTTON_NEW_PRODUCTS_TO_CATEGORIES', 'Select Another Product by ID#');
define('BUTTON_CATEGORY_LISTING', 'Category Listing');
define('TEXT_SET_PRODUCTS_TO_CATEGORIES_LINKS', 'Show Product to Categories Links for: ');

define('TEXT_INFO_PRODUCTS_TO_CATEGORIES_LINKER_INTRO', 'This product is currently linked to the categories selected below.<br />To add/remove links, select/deselect the checkboxes as required and then click on the ' . BUTTON_UPDATE_CATEGORY_LINKS . ' button. Set the number of Category columns <a href="' . FILENAME_DEFAULT . '.php?cmd=configuration&amp;gID=3&amp;cID=107&amp;action=edit">here</a>.<br /><br />Note that additional product/category actions are available using the Global Tools below.');

define('TEXT_INFO_MASTER_CATEGORY_CHANGE','A product has a Master Category ID (for pricing purposes) that can be considered as the category where the product actually <i>resides</i>. Additionally, a product can be <i>linked</i> (copied) to any number of other categories, where the price may be modified due to conditions on those linked categories.<br />The Master Category ID can be changed by using this Master Category dropdown, that only offers the <strong>currently linked</strong> categories as possible alternatives.<br />To set the Master Category ID to <strong>another</strong> category, first link it to the new category using the table below, and Update. Then use this dropdown to reassign the master category to the new category.');

define('TEXT_SET_MASTER_CATEGORIES_ID', '<strong>WARNING:</strong> a MASTER CATEGORIES ID must be assigned');
define('WARNING_PRODUCT_UNLINKED_FROM_CATEGORY', 'The product was unlinked from the previously selected category "%1$s" ID#%2$u, and so is shown in it\'s master category.');

define('WARNING_PRODUCTS_LINK_TO_CATEGORY_REMOVED', 'WARNING: Product has been reset and is no longer part of this Category...');//when category set, but no product filter set

define('SUCCESS_MASTER_CATEGORIES_ID', 'Successful update of Product to Categories Links ...');

define('WARNING_MASTER_CATEGORIES_ID', 'WARNING: No Master Category is set for Product ID#%u!<br />This MUST be corrected immediately.');

define('TEXT_PRODUCTS_ID_INVALID', 'WARNING: Product ID#%u is invalid/does not exist in the database.');

define('TEXT_LABEL_SELECT_ALL_OR_NONE', 'Select All or None');

//Global Tools
define('HEADER_CATEGORIES_GLOBAL_TOOLS', 'Global Product/Category Tools');
define('TEXT_PRODUCTS_ID_NOT_REQUIRED', 'Note: A Product does not need to be selected to use all these tools. However, selecting a Product above will display the Categories and their ID numbers.');
///////////////////////
// copy all products from category source to category target as linked
define('TEXT_HEADING_COPY_ALL_PRODUCTS_TO_CATEGORY_LINKED', 'Link (copy) Products from one Category to another Category');
define('TEXT_INFO_COPY_ALL_PRODUCTS_TO_CATEGORY_LINKED', 'Example: a Copy from SOURCE Category ID#8 to TARGET Category ID#22 will create linked copies of ALL the products that are in Category 8, into Category 22.');
define('TEXT_LABEL_COPY_ALL_PRODUCTS_TO_CATEGORY_FROM_LINKED', 'Select ALL products from the SOURCE Category ID#: ');
define('TEXT_LABEL_COPY_ALL_PRODUCTS_TO_CATEGORY_TO_LINKED', 'Link (copy) to the TARGET Category ID#: ');
define('BUTTON_COPY_CATEGORY_LINKED', 'Copy Products as Linked');
define('WARNING_CATEGORY_SOURCE_NOT_EXIST','<strong>SOURCE</strong> Category ID#%u invalid (does not exist)');
define('WARNING_CATEGORY_TARGET_NOT_EXIST','<strong>TARGET</strong> Category ID#%u invalid (does not exist)');
define('WARNING_CATEGORY_IDS_DUPLICATED', 'Warning: same Category IDs (#%u)');
define('WARNING_CATEGORY_NO_PRODUCTS', '<strong>SOURCE</strong> Category ID#%u invalid (contains no products)');
define('WARNING_CATEGORY_SUBCATEGORIES', '<strong>TARGET</strong> Category ID#%u invalid (contains subcategories)');
define('WARNING_NO_CATEGORIES_ID', 'Warning: no categories were selected ... no changes were made');
define('SUCCESS_COPY_LINKED', '%1$u product(s) copied (linked), from SOURCE Category ID#%2$u to TARGET Category ID#%3$u');
define('WARNING_COPY_FROM_IN_TO_LINKED', 'WARNING: No products copied (all products in Category ID#%1$u are already linked into Category ID#%2$u)');
////////////////////////
// remove products in reference category from linked category
define('TEXT_HEADING_REMOVE_ALL_PRODUCTS_FROM_CATEGORY_LINKED', 'Remove Linked Products from a Category');
define('TEXT_INFO_REMOVE_ALL_PRODUCTS_TO_CATEGORY_LINKED', 'Example: Using Reference Category #8 and Target Category #22 will remove any linked products from the target Category #22 that exist in the reference Category #8. No product in target Category #22 can have a master category ID of #22 (if so, it must be reassigned to another category).');
define('TEXT_LABEL_REMOVE_ALL_PRODUCTS_TO_CATEGORY_FROM_LINKED', 'Select ALL Products in the Reference Category: ');
define('TEXT_LABEL_REMOVE_ALL_PRODUCTS_TO_CATEGORY_TO_LINKED', 'Remove Any Linked Products from the Target Category: ');
define('BUTTON_REMOVE_CATEGORY_LINKED', 'Remove Linked Products');
define('SUCCESS_REMOVE_LINKED', '%1$u linked product(s) removed from Category ID#%2$u');
define('WARNING_REMOVE_FROM_IN_TO_LINKED', 'WARNING: No changes made: no products in Target Category ID#%1$u are linked from Reference Category ID#%2$u');
define('WARNING_REMOVE_LINKED_PRODUCTS_MASTER_CATEGORIES_ID_CONFLICT', '<strong>WARNING: MASTER CATEGORIES ID CONFLICT!</strong><br />Reference Category ID#%1$u for removal of linked products in Target Category ID#%2$u.<br />You have requested the removal of some linked products from a target category. One or more of those products has the same master category ID as the target category. This means that the product is not "linked" to the target category but "resides" in that category and so cannot be removed as part of this request to remove <i>linked</i> products.<br />If you wish to <i>retain</i> this product, you must change it\'s master category ID to another category (i.e. "Move" it) before carrying out this process again. This may be done on this page or via the "Move" action on a Category-Product listing page. The first product with a conflicting master category ID has been already selected for editing.<br/>If you wish to <i>delete</i> this product, you must use the "Delete" action on the Category-Product listing page.<br /><br /><strong>Products(s) with a conflicting master category ID:</strong><br />%3$s');

// Copy linked categories from one product to another
define('TEXT_HEADING_COPY_LINKED_CATEGORIES', 'Copy Linked Categories to Another Product');
define('TEXT_INFO_COPY_LINKED_CATEGORIES', 'Copy the linked categories (but not the master category) of the <strong>currently selected product</strong> to another product.');
define('TEXT_OPTION_LINKED_CATEGORIES', 'Select the target product');
define('BUTTON_COPY_LINKED_CATEGORIES', 'Copy linked categories');
define('SUCCESS_LINKED_CATEGORIES_COPIED_TO_TARGET_PRODUCT', 'The list of linked categories has been copied to the target product: %s');
define('WARNING_COPY_LINKED_CATEGORIES', 'A target product was not selected!');

// Set a new master_categories_id to all products in a category
define('TEXT_HEADING_RESET_ALL_PRODUCTS_TO_CATEGORY_MASTER', 'Reset the Master Category ID for ALL Products in a Category');
define('TEXT_INFO_RESET_ALL_PRODUCTS_TO_CATEGORY_MASTER', 'Example: Resetting Category 22 will assign a Master Category ID of 22 to ALL the products in Category 22, .');
define('TEXT_INFO_RESET_ALL_PRODUCTS_TO_CATEGORY_FROM_MASTER', 'Reset the Master Category ID for All Products in Category: ');
define('BUTTON_RESET_CATEGORY_MASTER', 'Reset Master Categories ID');
define('SUCCESS_RESET_ALL_PRODUCTS_TO_CATEGORY_FROM_MASTER', 'All products in Category ID#%1$d have been reset to have Master Category ID#%1$d');
define('TEXT_CATEGORIES_NAME', 'Categories Name');
