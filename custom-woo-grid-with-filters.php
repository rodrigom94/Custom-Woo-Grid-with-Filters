<?php
/**
 * Plugin Name: Custom Woo Grid with Filters
 * Version: 2.0.12
 * Description: Custom Woo Grid with Filters
 * Author: rod_melgarejo
 * Author URI: 
 * Requires at least: 5.0.0
 * Tested up to: 6.3.1
 * Text Domain: custom-woo-grid-with-filters
 * Domain Path: /lang/
 * 
 * WC requires at least: 7.0.0
 * WC tested up to: 8.2.0
 * 
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) === false) {
    // Deactivate the plugin
    deactivate_plugins(plugin_basename(__FILE__));

    // Display admin error message
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Custom Woo Grid with Filters requires WooCommerce to be active. The plugin has been deactivated.', 'custom-woo-grid-with-filters');
        echo '</p></div>';
    });

    // Do not proceed with plugin loading
    return;
}

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('custom-woo-grid-style', plugin_dir_url(__FILE__) . 'assets/style.css');
    wp_enqueue_script('custom-woo-grid-script', plugin_dir_url(__FILE__) . 'assets/scripts.js', array('jquery'), null, true);
    wp_localize_script('custom-woo-grid-script', 'my_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
});

function obtener_productos($meta_key = '') {
    $args = array(
        'post_type' => 'product',
        'numberposts' => -1,
    );
    $productos = get_posts($args);

    if (empty($meta_key)) {
        return $productos;
    }

    $resultados = array();
    foreach ($productos as $producto) {
        $meta_value = get_post_meta($producto->ID, $meta_key, true);
        if (!empty($meta_value) && !in_array($meta_value, $resultados)) {
            $resultados[] = $meta_value;
        }
    }

    sort($resultados);
    return $resultados;
}

function obtener_categorias_producto() {
    $categorias = get_terms('product_cat', array('hide_empty' => true));

    return array_map(function($categoria) {
        $count = count(get_posts(array(
            'post_type' => 'product',
            'numberposts' => -1,
            'tax_query' => array(
                array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $categoria->term_id),
            ),
            'fields' => 'ids',
        )));
        $categoria->count = $count;
        return $categoria;
    }, $categorias);
}

function obtener_atributos_producto() {
    $atributos = wc_get_attribute_taxonomies();

    return array_map(function($atributo) {
        $terms = get_terms(array('taxonomy' => wc_attribute_taxonomy_name($atributo->attribute_name), 'hide_empty' => true));
        return array_map(function($term) use ($atributo) {
            $count = count(get_posts(array(
                'post_type' => 'product',
                'numberposts' => -1,
                'tax_query' => array(
                    array('taxonomy' => wc_attribute_taxonomy_name($atributo->attribute_name), 'field' => 'term_id', 'terms' => $term->term_id),
                ),
                'fields' => 'ids',
            )));
            $term->count = $count;
            return $term;
        }, $terms);
    }, $atributos);
}

function generar_opciones_select($opciones, $placeholder) {
    $html = '<option value="">' . esc_html($placeholder) . '</option>';
    foreach ($opciones as $opcion) {
        $html .= '<option value="' . esc_attr($opcion->slug) . '">' . esc_html($opcion->name) . ' (' . $opcion->count . ')</option>';
    }
    return $html;
}

function obtener_categorias_producto_para_select() {
    $categorias = obtener_categorias_producto();
    return generar_opciones_select($categorias, 'Seleccionar categoría');
}

function mostrar_productos_grillawoo($is_ajax_request = false) {
    ob_start();

    // Obtener el parámetro de la URL
    $cwr_product_id = isset($_GET['cwr_product_id']) ? intval($_GET['cwr_product_id']) : 0;

    $paged = isset($_POST['page']) ? $_POST['page'] : 1;
    $busqueda = isset($_POST['busqueda']) ? sanitize_text_field($_POST['busqueda']) : '';
    $categorias = isset($_POST['categorias']) ? array_map('sanitize_text_field', $_POST['categorias']) : array();
    $atributos = isset($_POST['atributos']) ? $_POST['atributos'] : array();
    $tax_query = array('relation' => 'AND');

    if ($cwr_product_id) {
        // Si el parámetro cwr_product_id está presente, modificar la consulta para obtener solo ese producto
        $args = array(
            'post_type' => 'product',
            'p' => $cwr_product_id
        );
    } else {
        // Continuar con la consulta normal
        if (!empty($categorias)) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $categorias,
                'operator' => 'IN'
            );
        }
    
        if (!empty($atributos)) {
            foreach ($atributos as $taxonomy => $terms) {
                if (!empty($terms)) {
                    $tax_query[] = array(
                        'taxonomy' => sanitize_key($taxonomy),
                        'field' => 'slug',
                        'terms' => array_map('sanitize_text_field', $terms),
                        'operator' => 'IN'
                    );
                }
            }
        }
    
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 9,
            'paged' => $paged,
            'tax_query' => $tax_query,
            'orderby' => 'title',  // Ordenar por título
            'order' => 'ASC'       // En orden ascendente
        );
    }

    $loop = new WP_Query($args);
    $total_pages = $loop->max_num_pages;
    $query = $loop->request;

    if (!$is_ajax_request) {
        ?>
        <!-- Main Script for Buscador -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.buttonContainer__buscar').forEach(function(container) {
                    const input = container.querySelector('.buttonContainer__buscar__input');
                    const resultsContainer = container.querySelector('.search-results');

                    if (resultsContainer) {
                        input && input.addEventListener('keyup', function() {
                            const query = input.value;

                            if (query.length > 0) {
                                jQuery.ajax({
                                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                    type: 'GET',
                                    data: {
                                        action: 'search_products',
                                        query: query
                                    },
                                    success: function(response) {
                                        resultsContainer.innerHTML = response;
                                        resultsContainer.style.display = 'block';
                                    }
                                });
                            } else {
                                resultsContainer.style.display = 'none';
                            }
                        });

                        document.addEventListener('click', function(e) {
                            if (!resultsContainer.contains(e.target) && e.target !== input) {
                                resultsContainer.style.display = 'none';
                            }
                        });
                    }
                });
            });
        </script>
        <!-- Contenedor principal -->
        <div class="grillawoo__container">
            
            <!-- Filtro de productos y productos -->
            <div class="outlet_container__filtros">
                <div class="filtradoPor">
                    <h2>Filtrado por:</h2>
                    <button id="clear-filters"><span class="icon-box">×</span> Limpiar</button>
                </div>
                <!-- Buscador -->
                <div class="buttonContainer__buscar">
                    <input class="buttonContainer__buscar__input" placeholder="Buscar...">
                    <div class="search-results" style="display: none;"></div>
                </div>
                <!-- Filtro de categorías -->
                <h2 class="isFilterRd__filterTitle">Categoría</h2>
                <ul id="filtro-categoria" class="isFilterRd">
                    <?php foreach (obtener_categorias_producto() as $categoria) : ?>
                        <li>
                            <input type="checkbox" id="cat-<?php echo $categoria->term_id; ?>" value="<?php echo $categoria->slug; ?>">
                            <label for="cat-<?php echo $categoria->term_id; ?>"><?php echo $categoria->name; ?></label>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Filtro de atributos -->
                <?php foreach (obtener_atributos_producto() as $atributo) : ?>
                    <?php if (empty($atributo)) continue; ?>
                    <h2 class="isFilterRd__filterTitle"><?php echo wc_attribute_label($atributo[0]->taxonomy); ?></h2>
                    <ul id="filtro-<?php echo $atributo[0]->taxonomy; ?>" class="isFilterRd">
                        <?php foreach ($atributo as $term) : ?>
                            <li>
                                <input type="checkbox" id="attr-<?php echo $term->term_id; ?>" value="<?php echo $term->slug; ?>">
                                <label for="attr-<?php echo $term->term_id; ?>"><?php echo $term->name; ?></label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div>
            <div id="productos-grillawoo">
                <!-- Video de YouTube -->
                <div id="youtube-video-container" style="display:none;">
                    <div class="youtube-video-wrapper">
                        <iframe id="youtube-player" width="560" height="315" src="https://www.youtube.com/embed/4sVPBVhOAgU?enablejsapi=1" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                        <button id="close-youtube-video">Cerrar Video</button>
                    </div>
                </div>
                <div class="productos-container">
        <?php
    } else {
        echo '<div class="productos-container">';
    }

    $product_count = 0;

    if ($loop->have_posts()) :
        while ($loop->have_posts()) : $loop->the_post();
            global $product;
            $product_count++;
            ?>

            <div class="producto-wrapper">
                <div class="producto">
                    <?php
                    $image_url = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'single-post-thumbnail');
                    if ($image_url) : ?>
                        <img src="<?php echo esc_url($image_url[0]); ?>" alt="<?php the_title(); ?>" width="400" height="300">
                    <?php endif; ?>

                    <div class="marca-producto"><?php echo esc_html(get_post_meta($product->get_id(), 'brand_name', true)); ?></div>
                    <h2><?php the_title(); ?></h2>
                    <div class="producto-descripcion"><?php echo wp_kses_post($product->get_short_description()); ?></div>
                </div>
            </div>

        <?php endwhile; ?>

        <?php
        // Agregar elementos vacíos si hay menos de 9 productos
        while ($product_count < 9) {
            echo '<div class="producto-wrapper"><div class="producto empty-item"></div></div>';
            $product_count++;
        }
        ?>
        </div> <!-- Cierra .productos-container -->
        <?php if ($total_pages > 1) : ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                    <a href="#" class="page-numbers<?php echo ($i == $paged) ? ' active' : ''; ?>" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <p><?php _e('No se encontraron productos.'); ?></p>
        </div> <!-- Cierra .productos-container -->
    <?php endif;

    if (!$is_ajax_request) {
        ?>
            </div>
        </div>
        <?php
    }

    $productos_html = ob_get_clean();
    if ($is_ajax_request) {
        wp_send_json(array(
            'productos_html' => $productos_html,
            'total_pages' => $total_pages,
            'pagination' => render_pagination($paged, $total_pages), // Agregar paginación aquí
            'query' => $tax_query
        ));
        wp_die();
    }
    return $productos_html;
}

function render_pagination($current_page, $total_pages) {
    $pagination_html = '<div class="pagination">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $pagination_html .= '<a href="#" class="page-numbers' . ($i == $current_page ? ' active' : '') . '" data-page="' . $i . '">' . $i . '</a>';
    }
    $pagination_html .= '</div>';
    return $pagination_html;
}

add_shortcode('mostrar_grillawoo', 'mostrar_productos_grillawoo');

function grillawoo_ajax_pagination() {
    mostrar_productos_grillawoo(true);
    wp_die();
}

add_action('wp_ajax_nopriv_grillawoo_ajax_pagination', 'grillawoo_ajax_pagination');
add_action('wp_ajax_grillawoo_ajax_pagination', 'grillawoo_ajax_pagination');

function search_products() {
    global $wpdb;
    $query = sanitize_text_field($_GET['query']);

    // Definir términos personalizados con imágenes
    $custom_terms = json_decode(get_option('custom_woo_grid_terms', '[]'), true);

    // Filtrar términos personalizados por el query
    $filtered_custom_terms = array_filter($custom_terms, function($term) use ($query) {
        return stripos($term['text'], $query) !== false;
    });

    // Primero obtener productos que comiencen con la consulta
    $args_starts_with = array(
        'post_type' => 'product',
        'posts_per_page' => -1, // Obtener todos los productos que coincidan
        'orderby' => 'title',
        'order' => 'ASC',
        's' => $query,
    );

    add_filter('posts_where', 'title_starts_with', 10, 2);
    $products_starts_with = new WP_Query($args_starts_with);
    remove_filter('posts_where', 'title_starts_with', 10, 2);

    // Luego obtener productos que contengan la consulta pero no comiencen con ella
    $args_contains = array(
        'post_type' => 'product',
        'posts_per_page' => -1, // Obtener todos los productos que coincidan
        'orderby' => 'title',
        'order' => 'ASC',
        's' => $query,
    );

    add_filter('posts_where', 'title_contains', 10, 2);
    $products_contains = new WP_Query($args_contains);
    remove_filter('posts_where', 'title_contains', 10, 2);

    $products = array_merge($products_starts_with->posts, $products_contains->posts);

    // Primero mostrar términos personalizados
    if (!empty($filtered_custom_terms)) {
        foreach ($filtered_custom_terms as $term) {
            echo '
            <div >
                <a class="search-result-item custom-term" href="' . esc_url($term['link']) . '" >
                    <div class="search-result-item__img">
                        <img src="' . esc_url($term['image']) . '" alt="' . esc_attr($term['text']) . '">
                    </div>
                    <div class="search-result-item__title">' . esc_html($term['text']) . '</div>
                </a>
            </div>';
        }
    }

    // Luego mostrar productos
    if (!empty($products)) {
        foreach ($products as $product) {
            $product_id = $product->ID;
            $product_title = $product->post_title;
            $thumbnail = get_the_post_thumbnail_url($product_id, 'thumbnail');
            echo '
            <div class="search-result-item" data-product-id="' . esc_attr($product_id) . '">
                <div class="search-result-item__img">
                    <img src="' . esc_url($thumbnail) . '" alt="' . esc_attr($product_title) . '">
                </div>
                <div class="search-result-item__title">' . esc_html($product_title) . '</div>
            </div>';
        }
    } else {
        echo '<div class="search-result-item np">No se encontraron productos.</div>';
    }

    wp_die();
}
add_action('wp_ajax_search_products', 'search_products');
add_action('wp_ajax_nopriv_search_products', 'search_products');

function title_starts_with($where, $wp_query) {
    global $wpdb;
    if ($search_term = $wp_query->get('s')) {
        $where .= " AND " . $wpdb->posts . ".post_title LIKE '" . esc_sql($wpdb->esc_like($search_term)) . "%'";
    }
    return $where;
}

function title_contains($where, $wp_query) {
    global $wpdb;
    if ($search_term = $wp_query->get('s')) {
        $where .= " AND " . $wpdb->posts . ".post_title LIKE '%" . esc_sql($wpdb->esc_like($search_term)) . "%'";
        $where .= " AND " . $wpdb->posts . ".post_title NOT LIKE '" . esc_sql($wpdb->esc_like($search_term)) . "%'";
    }
    return $where;
}

function redirect_to_product_page() {
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.search-results').forEach(function(resultsContainer) {
                resultsContainer.addEventListener('click', function(e) {
                    const item = e.target.closest('.search-result-item');
                    if (item) {
                        const productId = item.getAttribute('data-product-id');
                        if(item.href == undefined){
                            if (window.location.href.includes("https://monge.pe/home-new/")) {
                                window.location.href = '<?php echo home_url(); ?>/home-new/?cwr_product_id=' + productId + '#productos';
                            } else { 
                                window.location.href = '<?php echo home_url(); ?>/?cwr_product_id=' + productId + '#productos';
                            }
                        }

                    }
                });
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'redirect_to_product_page');



add_action('admin_menu', 'custom_woo_grid_add_admin_menu');
function custom_woo_grid_add_admin_menu() {
    add_menu_page(
        'Woo Grid with Filters',
        'Woo Grid with Filters',
        'manage_options',
        'woo-grid-with-filters',
        'custom_woo_grid_admin_page',
        'dashicons-filter',
        20
    );
}


function custom_woo_grid_admin_page() {
    // Verificar que el usuario tiene permisos
    if (!current_user_can('manage_options')) {
        return;
    }

    // Procesar la forma al enviar
    if (isset($_POST['custom_terms'])) {
        update_option('custom_woo_grid_terms', sanitize_text_field(wp_json_encode($_POST['custom_terms'])));
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    // Obtener términos personalizados almacenados
    $custom_terms = json_decode(get_option('custom_woo_grid_terms', '[]'), true);
    ?>
    <div class="wrap">
        <h1>Woo Grid with Filters</h1>
        <form method="post">
            <table class="form-table" id="custom-terms-table">
                <thead>
                    <tr>
                        <th>Text</th>
                        <th>Link</th>
                        <th>Image URL</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($custom_terms)): ?>
                        <?php foreach ($custom_terms as $index => $term): ?>
                            <tr>
                                <td><input type="text" name="custom_terms[<?php echo $index; ?>][text]" value="<?php echo esc_attr($term['text']); ?>"></td>
                                <td><input type="text" name="custom_terms[<?php echo $index; ?>][link]" value="<?php echo esc_attr($term['link']); ?>"></td>
                                <td><input type="url" name="custom_terms[<?php echo $index; ?>][image]" value="<?php echo esc_attr($term['image']); ?>"></td>
                                <td><button type="button" class="button remove-term-button">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="button" class="button" id="add-term-button">Add Term</button>
            <p class="submit">
                <input type="submit" class="button-primary" value="Save Changes">
            </p>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addButton = document.getElementById('add-term-button');
            const tableBody = document.querySelector('#custom-terms-table tbody');
            let termIndex = <?php echo count($custom_terms); ?>;

            addButton.addEventListener('click', function() {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><input type="text" name="custom_terms[${termIndex}][text]"></td>
                    <td><input type="text" name="custom_terms[${termIndex}][link]"></td>
                    <td><input type="url" name="custom_terms[${termIndex}][image]"></td>
                    <td><button type="button" class="button remove-term-button">Remove</button></td>
                `;
                tableBody.appendChild(row);
                termIndex++;
            });

            tableBody.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-term-button')) {
                    e.target.closest('tr').remove();
                }
            });
        });
    </script>
    <?php
}

?>
