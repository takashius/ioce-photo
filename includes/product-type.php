<?php
// Registrar los tipos de productos personalizados
add_action('init', 'registrar_productos_personalizados');
function registrar_productos_personalizados()
{
  class WC_Product_Foto extends WC_Product
  {
    public function __construct($product)
    {
      $this->set_props(array(
        'product_type' => 'foto'
      ));
      parent::__construct($product);
    }
  }

  class WC_Product_Foto_Folder extends WC_Product
  {
    public function __construct($product)
    {
      $this->set_props(array(
        'product_type' => 'foto_folder'
      ));
      parent::__construct($product);
    }
  }
}

// Agregar tipos de productos personalizados al selector de tipos de productos
add_filter('product_type_selector', 'agregar_tipos_productos_personalizados');
function agregar_tipos_productos_personalizados($types)
{
  $types['foto'] = __('Foto', 'woocommerce');
  $types['foto_folder'] = __('Foto Folder', 'woocommerce');
  return $types;
}
