<?php

function logError($message)
{
    $timestamp = date('d M Y H:i:s');
    error_log("[{$timestamp}] {$message}\n", 3, __DIR__ . "/import_" . date('Y-m-d') . ".txt");
}

function splitProducts()
{
    logError("Start Import");
    $categories = array(
        // 'AA Speaker Singles' => array(
        //     'A-D',
        //     'E-H',
        //     'I-L',
        //     'M-P',
        //     'Q-T',
        //     'U-Z'
        // ),
        'Al-Anon Speaker Singles' => array(
            'A-D',
            'E-H',
            'I-L',
            'M-P',
            'Q-T',
            'U-Z'
        )
    );

    foreach ($categories as $category => $ranges) {
        logError("Processing Category: " . $category);

        $products_query = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'name',
                    'terms' => $category,
                ),
            ),
        ]);

        $tracks = array();

        if (!$products_query->have_posts()) {
            continue;
        }

        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product_name = get_the_title();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            $variations = $product->get_available_variations();

            foreach ($variations as $variation) {
                $variation_id = $variation['variation_id'];
                $variation_obj = new WC_Product_Variation($variation_id);

                if ($variation_obj->is_downloadable()) {
                    $downloadable_files = $variation_obj->get_downloads();

                    foreach ($downloadable_files as $file) {

                        $tracks[] = array(
                            'name' => $product_name,
                            'author' => '',
                            'url' => $file['file'],
                        );
                    }
                }
            }
        }

        logError("Found Products" . count($tracks));

        usort($tracks, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });


        foreach ($ranges as $range) {
            $playlist = get_page_by_title("{$category} {$range}", OBJECT, 'bb_playlist_player');
            if ($playlist) {
                $playlist_id = $playlist->ID;
            } else {
                $playlist_id = wp_insert_post([
                    'post_type' => 'bb_playlist_player',
                    'post_title' => "{$category} {$range}",
                    'post_status' => 'publish',
                ]);

                if (!$playlist_id) {
                    echo 'Failed to create post for category: <br>';
                    continue;
                }
            }


            $filteredTracks = getRangedTracks($tracks, $range);
            logError("Filter Products for range: {$range}:" . count($filteredTracks));
            wp_set_object_terms($playlist_id, $category, 'playlist_category', false);
            update_post_meta($playlist_id, 'bb_playlist', $filteredTracks);
            logError("Store completed successfully");
        }

        logError("Completed\n\n");

        wp_reset_postdata();
    }
}

function getRangedTracks($tracks, $range)
{
    $ranges = array(
        'A-D' => array('A', 'D'),
        'E-H' => array('E', 'H'),
        'I-L' => array('I', 'L'),
        'M-P' => array('M', 'P'),
        'Q-T' => array('Q', 'T'),
        'U-Z' => array('U', 'Z')
    );

    if (!isset($ranges[$range])) {
        return [];
    }

    $lower_bound = $ranges[$range][0];
    $upper_bound = $ranges[$range][1];

    $filtered_tracks = [];

    foreach ($tracks as $track) {
        $track_name = $track['name'];
        $first_char = strtolower($track_name[0]);

        if (strcasecmp($first_char, $lower_bound) >= 0 && strcasecmp($first_char, $upper_bound) <= 0) {
            $filtered_tracks[] = $track;
        }
    }

    return $filtered_tracks;
}
