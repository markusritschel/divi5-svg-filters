<?php
// Module preset for divi/text with an SVG filter (run with `wp eval-file`).
$name  = \ET\Builder\Packages\GlobalData\GlobalPreset::option_name();
$data  = et_get_option( $name, array(), '', true, false, '', '', true );
$data  = is_array( $data ) ? $data : array();
$attrs = array( 'module' => array( 'decoration' => array( 'filters' => array( 'desktop' => array( 'value' => array( 'sepia' => '40%', 'svgFilter' => 'fx-grain' ) ) ) ) ) );

$data['module']['divi/text']['default']             = $data['module']['divi/text']['default'] ?? '';
$data['module']['divi/text']['items']['e2epreset1'] = array(
	'id'         => 'e2epreset1',
	'name'       => 'E2E SVG preset',
	'moduleName' => 'divi/text',
	'version'    => '5.15.1',
	'type'       => 'module',
	'created'    => time() * 1000,
	'updated'    => time() * 1000,
	'attrs'      => $attrs,
	'styleAttrs' => $attrs,
	'renderAttrs'=> array(),
);
et_update_option( $name, $data, false, '', '', true );
echo "preset e2epreset1\n";
