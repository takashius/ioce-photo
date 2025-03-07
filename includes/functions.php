<?php
// Añadir campos personalizados para los productos
add_action('woocommerce_product_options_general_product_data', 'agregar_campos_personalizados');
function agregar_campos_personalizados()
{
  echo '<div class="options_group show_if_foto">';
  woocommerce_wp_text_input(array(
    'id' => '_marca_agua',
    'label' => __('Imagen con Marca de Agua (URL)', 'woocommerce'),
    'desc_tip' => 'true',
    'description' => __('Introduce la URL de la imagen con marca de agua.', 'woocommerce'),
  ));
  woocommerce_wp_text_input(array(
    'id' => '_alta_resolucion',
    'label' => __('Imagen en Alta Resolución (URL)', 'woocommerce'),
    'desc_tip' => 'true',
    'description' => __('Introduce la URL de la imagen en alta resolución.', 'woocommerce'),
  ));
  echo '</div>';

  echo '<div class="options_group show_if_foto_folder">';
  woocommerce_wp_text_input(array(
    'id' => '_folder_url',
    'label' => __('URL del Folder', 'woocommerce'),
    'desc_tip' => 'true',
    'description' => __('Introduce la direccion de la carpeta con las imagenes.', 'woocommerce'),
  ));
  woocommerce_wp_text_input(array(
    'id' => '_gallery_name',
    'label' => __('Nombre de la galeria', 'woocommerce'),
    'desc_tip' => 'true',
    'description' => __('Nombre que tendra el conjunto de fotos.', 'woocommerce'),
  ));
  echo '</div>';

  woocommerce_wp_text_input(array(
    'id' => '_regular_price',
    'label' => __('Precio Regular', 'woocommerce'),
    'desc_tip' => 'true',
    'description' => __('Introduce el precio del producto.', 'woocommerce'),
    'type' => 'number',
    'custom_attributes' => array(
      'step' => 'any',
      'min' => '0'
    )
  ));
}

// Guardar campos personalizados y establecer la imagen del producto y las descargas
add_action('woocommerce_process_product_meta', 'guardar_campos_personalizados');
function guardar_campos_personalizados($post_id)
{
  $marca_agua = $_POST['_marca_agua'];
  if (!empty($marca_agua)) {
    update_post_meta($post_id, '_marca_agua', esc_attr($marca_agua));

    // Establecer la imagen del producto
    $image_id = attachment_url_to_postid($marca_agua);
    if ($image_id) {
      set_post_thumbnail($post_id, $image_id);
    }
  }
  $alta_resolucion = $_POST['_alta_resolucion'];
  if (!empty($alta_resolucion)) {
    update_post_meta($post_id, '_alta_resolucion', esc_attr($alta_resolucion));

    // Generar miniatura de la imagen de alta resolución
    $image_path = get_attached_file(attachment_url_to_postid($alta_resolucion));
    $thumbnail_path = wp_get_upload_dir()['path'] . '/thumbnail-' . basename($image_path);
    $image_editor = wp_get_image_editor($image_path);

    if (!is_wp_error($image_editor)) {
      $image_editor->resize(150, 150, true); // Ajusta el tamaño según sea necesario
      $image_editor->save($thumbnail_path);
      update_post_meta($post_id, '_thumbnail_path', $thumbnail_path);
    }

    // Configurar la descarga del producto
    $downloadable_files = array(
      array(
        'name' => __('Imagen en Alta Resolución', 'woocommerce'),
        'file' => esc_url($alta_resolucion)
      )
    );
    update_post_meta($post_id, '_downloadable_files', $downloadable_files);
    update_post_meta($post_id, '_download_limit', -1);
    update_post_meta($post_id, '_download_expiry', '');
  }

  $folder_url = $_POST['_folder_url'];
  $gallery_name = $_POST['_gallery_name'];
  if (!empty($folder_url)) {
    wp_delete_post($post_id, true);
    $categoria = [$gallery_name];
    $precio = $_POST['_regular_price'];
    crear_productos_desde_carpeta(['url' => $folder_url, 'categoria' => $categoria, 'precio' => $precio]);

    $redirect_url = admin_url('edit.php?post_type=product');
    wp_redirect($redirect_url);
    exit;
  }
}

// Mostrar y ocultar dinámicamente los campos personalizados según el tipo de producto seleccionado
add_action('admin_footer', 'mostrar_ocultar_campos_personalizados');
function mostrar_ocultar_campos_personalizados()
{
  echo '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $("#product-type").change(function() {
                var productType = $(this).val();
                $(".options_group").hide();
                $(".show_if_" + productType).show();
            }).change();
        });
    </script>';
}

// Ocultar opciones de envío para productos de tipo 'Foto'
add_filter('woocommerce_product_needs_shipping', 'eliminar_opciones_envio', 10, 2);
function eliminar_opciones_envio($needs_shipping, $product)
{
  if ($product->get_type() === 'foto') {
    return false;
  }
  if ($product->get_type() === 'foto_folder') {
    return false;
  }
  return $needs_shipping;
}

// Asegurarse de que los productos 'Foto' sean descargables, virtuales y con inventario infinito
add_action('woocommerce_admin_process_product_object', 'configurar_producto_foto');
function configurar_producto_foto($product)
{
  if ($product->get_type() === 'foto') {
    $product->set_virtual(true);
    $product->set_downloadable(true);
    $product->set_stock_status('instock');
    $product->set_manage_stock(false);
    $product->set_catalog_visibility('visible');
  }
}
