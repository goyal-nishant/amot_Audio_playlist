<?php


// Hook into init to run custom function

// add_action('init', 'import');

// function import()
// {

//     if (isset($_GET['run-script'])) {

//         importFromWoocommerceProduct();

//     }

// }


function importFromWoocommerceProduct()
{
    generate_playlist_entries('AA Speaker Singles');
    generate_playlist_entries('Al-Anon Speaker Singles');
}
function generate_playlist_entries($category_name)
{
    $products_query = new WP_Query(
        array(
            'post_type' => 'product',
            'posts_per_page' => 1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'name',
                    'terms' => $category_name,
                )
            )
        )
    );


    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();

            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            $variations = $product->get_available_variations();
            $product_name = get_the_title();
            $product_content = get_the_content();

            $existing_entry = get_page_by_title($product_name, OBJECT, 'bb_playlist_player');

            $song_data = array();

            foreach ($variations as $variation) {
                $variation_id = $variation['variation_id'];
                $variation_obj = new WC_Product_Variation($variation_id);


                if ($variation_obj->is_downloadable()) {
                    $downloadable_files = $variation_obj->get_downloads();


                    foreach ($downloadable_files as $file_key => $file) {
                        $file_name = $file['name'];
                        $file_url = $file['file'];


                        $song_data[] = array(
                            'name' => $product_name,
                            'author' => '',
                            'url' => $file_url,
                        );
                    }
                }
            }

            usort($song_data, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            $post_data = array(
                'post_type' => 'bb_playlist_player',
                'post_title' => $product_name,
                'post_content' => $product_content,
                'post_status' => 'publish',
            );


            if ($existing_entry) {
                $post_data['ID'] = $existing_entry->ID;
                wp_update_post($post_data);

            } else {
                $post_id = wp_insert_post($post_data);
                if ($post_id) {
                    wp_set_object_terms($post_id, $category_name, 'playlist_category', false);
                }
            }

            if (!empty($song_data)) {

                update_post_meta($existing_entry ? $existing_entry->ID : $post_id, 'bb_playlist', $song_data);
            }
        }

        wp_reset_postdata();
    }
}

//new fun

add_action('init', 'importproduct');

function importproduct()
{
    if (isset($_GET['run-custom-script'])) {
        createPostsByCategoryName();
    }
}

function createPostsByCategoryName()
{
    $categories = array(
        'AA Speaker Singles',
        'Al-Anon Speaker Singles',
    );

    foreach ($categories as $category_name) {
        $existing_entry = get_page_by_title($category_name, OBJECT, 'bb_playlist_player');

        if (!$existing_entry) {
            $post_data = array(
                'post_type' => 'bb_playlist_player',
                'post_title' => $category_name,
                'post_status' => 'publish',
            );

            $post_id = wp_insert_post($post_data);

            if ($post_id) {
                wp_set_object_terms($post_id, $category_name, 'playlist_category', false);

                generate_playlist_entries_for_category($category_name, $post_id);

                echo 'Created post for category: ' . $category_name . '<br>';


            } else {
                echo 'Failed to create post for category: ' . $category_name . '<br>';
            }
        } else {
            $post_id = $existing_entry->ID;

            wp_set_object_terms($post_id, $category_name, 'playlist_category', false);

            generate_playlist_entries_for_category($category_name, $post_id);

            echo 'Updated category for post: ' . $category_name . '<br>';
        }
    }
}

function generate_playlist_entries_for_category($category_name, $post_id)
{
    $products_query = new WP_Query(
        array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'name',
                    'terms' => $category_name,
                )
            )
        )
    );

    $song_data = array();

    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();

            $product_id = get_the_ID();
            $product_name = get_the_title();
            $product = wc_get_product($product_id);
            $variations = $product->get_available_variations();

            foreach ($variations as $variation) {
                $variation_id = $variation['variation_id'];
                $variation_obj = new WC_Product_Variation($variation_id);

                if ($variation_obj->is_downloadable()) {
                    $downloadable_files = $variation_obj->get_downloads();

                    foreach ($downloadable_files as $file_key => $file) {
                        $file_name = $file['name'];
                        $file_url = $file['file'];

                        $song_data[] = array(
                            'name' => $product_name,
                            'author' => '',
                            'url' => $file_url,
                        );
                    }
                }
            }
        }

        usort($song_data, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        update_post_meta($post_id, 'bb_playlist', $song_data);

        error_log(json_encode($song_data), 3, __DIR__ . "/log.txt");

        wp_reset_postdata();
    }
}



?>