<?php

function crear_productos_desde_carpeta($atts)
{
  // Verificar que la URL y la categoría sean proporcionadas
  if (empty($atts['url']) || empty($atts['categoria'])) {
    return 'Por favor, proporciona una URL de carpeta y una categoría.';
  }

  // Obtener la ruta de la carpeta desde la URL
  $carpeta_path = ABSPATH . $atts['url'];

  // Verificar si la carpeta existe
  if (!is_dir($carpeta_path)) {
    return 'La carpeta especificada no existe.';
  }

  // Obtener todos los archivos de imagen en la carpeta
  $archivos = glob($carpeta_path . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);

  // Verificar si hay archivos de imagen en la carpeta
  if (empty($archivos)) {
    return 'No se encontraron archivos de imagen en la carpeta.';
  }

  // Crear productos para cada archivo de imagen
  foreach ($archivos as $archivo) {
    crear_producto_desde_imagen($archivo, $atts['categoria'], $atts['precio']);
  }

  return 'Productos creados exitosamente.';
}

// Crear un producto desde una imagen
function crear_producto_desde_imagen($imagen_path, $categoria, $precio)
{
  // Paso 1: Verificar si la imagen existe
  if (!file_exists($imagen_path)) {
    return new WP_Error('imagen_no_existe', 'La imagen no existe en la ruta especificada.');
  }

  // Paso 2: Crear miniatura de la imagen
  $upload_dir = wp_get_upload_dir();
  $thumbnail_path = $upload_dir['path'] . '/thumbnail-' . basename($imagen_path);
  error_log("Ruta de la miniatura: $thumbnail_path");

  // Agregar marca de agua
  $marca_agua_path = plugin_dir_path(dirname(__FILE__)) . 'images/logo.png'; // Ruta de la marca de agua
  if (file_exists($marca_agua_path)) {
    error_log("Marca de agua encontrada en: $marca_agua_path");

    // Usar la función para crear la miniatura con la marca de agua
    $resultado = aplicar_marca_de_agua_con_gd($imagen_path, $marca_agua_path, $thumbnail_path);

    if (!$resultado) {
      return new WP_Error('miniatura_error', 'No se pudo generar la miniatura con la marca de agua.');
    }
  } else {
    error_log("ERROR: No se encontró la marca de agua en la ruta especificada: $marca_agua_path");
  }

  // Paso 4: Subir la miniatura a la biblioteca de medios
  $file_array = array(
    'name' => basename($thumbnail_path),
    'tmp_name' => $thumbnail_path
  );

  $thumbnail_id = media_handle_sideload($file_array, 0);

  if (is_wp_error($thumbnail_id)) {
    return $thumbnail_id;
  }

  // Paso 5: Crear el producto
  $producto_id = wp_insert_post(array(
    'post_title' => basename($imagen_path),
    'post_content' => '',
    'post_status' => 'publish',
    'post_type' => 'product'
  ));

  if (is_wp_error($producto_id)) {
    return $producto_id;
  }

  // Paso 6: Establecer los metadatos del producto
  wp_set_object_terms($producto_id, 'simple', 'product_type');
  wp_set_object_terms($producto_id, $categoria, 'product_cat');

  // Convertir la ruta física de la imagen a una URL
  $imagen_url = str_replace(wp_get_upload_dir()['basedir'], wp_get_upload_dir()['baseurl'], $imagen_path);
  update_post_meta($producto_id, '_alta_resolucion', esc_url($imagen_url));

  update_post_meta($producto_id, '_regular_price', $precio);
  update_post_meta($producto_id, '_price', $precio);
  update_post_meta($producto_id, '_downloadable', 'yes');
  update_post_meta($producto_id, '_virtual', 'yes');

  // Crear el array de archivos descargables en el formato correcto
  $downloadable_files = array(
    md5($imagen_url) => array( // ID único basado en la URL
      'id' => md5($imagen_url), // ID único
      'name' => __('Imagen en Alta Resolución', 'woocommerce'),
      'file' => esc_url($imagen_url), // URL del archivo
      'enabled' => true // Habilitar el archivo
    )
  );
  update_post_meta($producto_id, '_downloadable_files', $downloadable_files);
  update_post_meta($producto_id, '_download_limit', -1);
  update_post_meta($producto_id, '_download_expiry', '');

  // Paso 7: Asignar la miniatura al producto
  set_post_thumbnail($producto_id, $thumbnail_id);

  return $producto_id; // Retorna el ID del producto creado
}

function aplicar_marca_de_agua_con_gd($imagen_path, $marca_agua_path, $thumbnail_path, $thumbnail_size = 150)
{
  // Cargar la imagen original
  $imagen_origen = imagecreatefromjpeg($imagen_path);
  if (!$imagen_origen) {
    error_log("ERROR: No se pudo cargar la imagen original desde $imagen_path.");
    return false;
  }

  // Obtener dimensiones originales
  $image_width = imagesx($imagen_origen);
  $image_height = imagesy($imagen_origen);
  error_log("Dimensiones de la imagen original: {$image_width}x{$image_height}");

  // Crear un lienzo cuadrado para la miniatura
  $miniatura = imagecreatetruecolor($thumbnail_size, $thumbnail_size);

  // Rellenar el fondo con transparencia
  $fondo_transparente = imagecolorallocatealpha($miniatura, 0, 0, 0, 127); // Color transparente
  imagefill($miniatura, 0, 0, $fondo_transparente);
  imagesavealpha($miniatura, true);

  // Redimensionar la imagen original al tamaño completo del recuadro (estirándola si es necesario)
  imagecopyresampled(
    $miniatura,
    $imagen_origen,
    0,
    0, // Sin desplazamiento, ocupará todo el lienzo
    0,
    0,
    $thumbnail_size,
    $thumbnail_size, // Tamaño final
    $image_width,
    $image_height      // Tamaño original
  );

  // Liberar memoria de la imagen original
  imagedestroy($imagen_origen);

  // Cargar la marca de agua
  $marca_agua = imagecreatefrompng($marca_agua_path);
  if (!$marca_agua) {
    error_log("ERROR: No se pudo cargar la marca de agua desde $marca_agua_path.");
    return false;
  }

  // Obtener dimensiones de la marca de agua
  $marca_width = imagesx($marca_agua);
  $marca_height = imagesy($marca_agua);
  error_log("Dimensiones de la marca de agua: {$marca_width}x{$marca_height}");

  // Escalar la marca de agua si es necesario
  $scale_marca = min($thumbnail_size / $marca_width, $thumbnail_size / $marca_height);
  $marca_scaled_width = (int)($marca_width * $scale_marca);
  $marca_scaled_height = (int)($marca_height * $scale_marca);
  $marca_redimensionada = imagecreatetruecolor($marca_scaled_width, $marca_scaled_height);

  // Mantener transparencia en la marca de agua
  imagealphablending($marca_redimensionada, false);
  imagesavealpha($marca_redimensionada, true);
  imagecopyresampled(
    $marca_redimensionada,
    $marca_agua,
    0,
    0,
    0,
    0,
    $marca_scaled_width,
    $marca_scaled_height,
    $marca_width,
    $marca_height
  );
  imagedestroy($marca_agua); // Liberar memoria de la marca de agua original

  // Calcular posición de la marca de agua (esquina inferior derecha)
  $marca_x = $thumbnail_size - $marca_scaled_width - 10; // 10px de margen
  $marca_y = $thumbnail_size - $marca_scaled_height - 10;

  // Aplicar la marca de agua a la miniatura
  imagecopy(
    $miniatura,
    $marca_redimensionada,
    $marca_x,
    $marca_y,
    0,
    0,
    $marca_scaled_width,
    $marca_scaled_height
  );
  imagedestroy($marca_redimensionada); // Liberar memoria

  // Guardar la miniatura final como PNG para conservar transparencia
  imagesavealpha($miniatura, true);
  $resultado = imagepng($miniatura, $thumbnail_path);

  // Liberar memoria de la miniatura
  imagedestroy($miniatura);

  if ($resultado) {
    error_log("Miniatura guardada correctamente en: $thumbnail_path");
  } else {
    error_log("ERROR: No se pudo guardar la miniatura.");
  }

  return $resultado;
}
