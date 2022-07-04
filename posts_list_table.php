<?php
/**
Plugin Name:  Posts List Table
Description: The plugin will display the list table in admin for post type 'Post'. Admin can Search, sort and paginate the list as required.
Author: Aakash Sharma
Author URI: https://profiles.wordpress.org/aakashsky/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: post-list-table
Version: 1.0.1
*/


/** 
Loading WP List Table class
*/
if (!class_exists('WP_List_Table')) {
      require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}


/** 
Extending WP List Table class
*/
class Posts_List_Table extends WP_List_Table
{
      private $posts_data;
		
	  // Showing results or list 	
      private function get_posts_data($search = "")
      {
            global $wpdb;

			$args = array('ID', 'post_title', 'post_author', 'post_date' );
			$sql_select = implode(', ', $args);
			#$sql_results = $wpdb->get_results("SELECT " . $sql_select . " FROM " . $wpdb->posts, ARRAY_A);

            if (!empty($search)) {
                  return $wpdb->get_results(
                        "SELECT " . $sql_select . " from {$wpdb->prefix}posts WHERE `post_type` = 'post' AND `post_status` = 'publish' AND post_title Like '%{$search}%' ", ARRAY_A );
            }else{
                  return $wpdb->get_results("SELECT " . $sql_select . " FROM " . $wpdb->posts ." WHERE `post_type` = 'post' AND `post_status` = 'publish' ", ARRAY_A);
            }
      }

      // Define table columns
      function get_columns()
      {
            $columns = array(
                  'cb' => '<input type="checkbox" />',
                  'ID' => 'ID',
                  'post_title' => 'Post Title',
				  'post_category' => __('Categories'),
                  'post_author'    => 'Author',
                  'post_date'      => 'Date'
            );
            return $columns;
      }

      // Bind table with columns, data and all
      function prepare_items()
      {
            if (isset($_POST['page']) && isset($_POST['s'])) {
                  $this->posts_data = $this->get_posts_data($_POST['s']);
            } else {
                  $this->posts_data = $this->get_posts_data();
            }

            $columns = $this->get_columns();
            $hidden = array();
            $sortable = $this->get_sortable_columns();
            $this->_column_headers = array($columns, $hidden, $sortable);

            /* pagination */
            $per_page = 5;
            $current_page = $this->get_pagenum();
            $total_items = count($this->posts_data);

            $this->posts_data = array_slice($this->posts_data, (($current_page - 1) * $per_page), $per_page);

            $this->set_pagination_args(array(
                  'total_items' => $total_items, // total number of items
                  'per_page'    => $per_page // items to show on a page
            ));

            usort($this->posts_data, array(&$this, 'usort_reorder'));

            $this->items = $this->posts_data;
      }

      // bind data with column
      function column_default($item, $column_name)
      {
            switch ($column_name) {
                  case 'ID':
                  case 'post_title':
                  case 'post_author':
                        return $item[$column_name];
						//return get_the_author_meta('display_name', $item['post_author']);
                  case 'post_date':
                        return ucwords($item[$column_name]);
                  default:
                        return print_r($item, true); //Show the whole array for troubleshooting purposes
            }
      }

      // To show checkbox with each row
      function column_cb($item)
      {
            return sprintf(
                  '<input type="checkbox" name="post_title[]" value="%s" />',
                  $item['ID']
            );
      }

      // Add sorting to columns
      protected function get_sortable_columns()
      {
            $sortable_columns = array(
                'ID' => array('ID', true),
				'post_title' => array('post_title', true),
				//'post_author' => array('post_author', true),
				'post_date' => array('post_date', true)
            );
            return $sortable_columns;
      }

      // Sorting function
      function usort_reorder($a, $b)
      {
            // If no sort, default to user_login
            $orderby = (!empty($_GET['orderby'])) ? $_GET['orderby'] : 'post_title';
            // If no order, default to asc
            $order = (!empty($_GET['order'])) ? $_GET['order'] : 'asc';
            // Determine sort order
            $result = strcmp($a[$orderby], $b[$orderby]);
            // Send final sort direction to usort
            return ($order === 'asc') ? $result : -$result;
      }


	function column_post_title($item) {
		$dlink = get_delete_post_link($item['ID'],'',false);
	    $actions = array(
			'edit'      => sprintf('<a href="post.php?post=%s&action=edit">Edit</a>', $item['ID']),
			'delete'    => sprintf('<a href="'.$dlink.'">Delete</a>'),
		);
	    return sprintf('%1$s %2$s', $item['post_title'], $this->row_actions($actions) );
	}
	
	function column_post_author($item) {
		$author_id = get_post_field ('post_author', $item['ID']);
		$display_name = get_the_author_meta( 'display_name' , $author_id ); 
		return $display_name;
	}

	//Display category column 
	function column_post_category($item) {
		
		$post_categories = wp_get_post_categories($item['ID']);
		$cats = array();			 
		foreach($post_categories as $c){
			$cat = get_category( $c );
			$cats[] = array( 'name' => $cat->name, 'slug' => $cat->slug );
		}
		//$c = get_categories(array('include' => $item['post_category'], 'hide_empty' => true));
		$newCat = wp_list_pluck($cats, 'name');
		$newCat = implode(', ', $newCat);
		return $newCat;
	}
}

/** 
Adding menu in WP backend
*/
function my_add_menu_items()
{
      add_menu_page('Posts List Table', 'Posts List Table', 'activate_plugins', 'post_list_table', 'all_posts_list_init');
}
add_action('admin_menu', 'my_add_menu_items');



/** 
Plugin menu callback function
*/
function all_posts_list_init()
{
      // Creating an instance of Class
      $postsTable = new Posts_List_Table();

      echo '<div class="wrap" id="my_post_list"><h2>All Posts List</h2>';
      // Prepare table
      $postsTable->prepare_items();
      ?>
		<form method="post">
			  <input type="hidden" name="page" value="post_list_table" />
			  <?php $postsTable->search_box('search', 'search_id'); ?>
		</form>
      <?php
	  // Display table
      $postsTable->display();
      echo '</div>';
}

