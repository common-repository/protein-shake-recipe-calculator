<?php
/*
Plugin Name: Protein Shake Recipe Calculator Widget
Plugin URI:
Description: Allows the user to calculate her optimum post-workout protein shake recipe and determine right mixture of three essential ingredients.
Author: Robert Wetzlmayr
Version: 1.3
Author URI: http://wetzlmayr.com/
License: GPL 2.0, @see http://www.gnu.org/licenses/gpl-2.0.html
*/

class wet_proteinshake {

    function init() {
    	// check for the required WP functions, die silently for pre-2.8 WP.
    	if (!function_exists('esc_js'))	return;

    	// load all l10n string upon entry
        load_plugin_textdomain('wet_proteinshake', false, dirname(plugin_basename(__FILE__)));

        // let WP know of this plugin's widget view entry
    	wp_register_sidebar_widget('wet_proteinshake', __('Protein Shake Recipe', 'wet_proteinshake'), array('wet_proteinshake', 'widget'),
            array(
            	'classname' => 'wet_proteinshake',
            	'description' => __('Allows the user to calculate her optimum post-workout protein shake recipe.', 'wet_proteinshake')
            )
        );

        // let WP know of this widget's controller entry
    	wp_register_widget_control('wet_proteinshake', __('Protein Shake Recipe', 'wet_proteinshake'), array('wet_proteinshake', 'control'),
    	    array('width' => 400)
        );

        // short code allows insertion of wet_proteinshake into regular posts as a [wet_proteinshake] tag.
        // From PHP in themes, call do_shortcode('wet_proteinshake');
        add_shortcode('wet_proteinshake', array('wet_proteinshake', 'shortcode'));
    }

	// back end options dialogue
	function control() {
		$options = shortcode_atts(
			array(
				'title'		=>	__('Calculate Your Protein Shake Recipe', 'wet_bmicalc'),
				'buttontext'=>	__('Calculate', 'wet_bmicalc'),
				'useounces'	=>	FALSE,
				'infohref' 	=> 	'http://whey-proteine.org/'
			),
			get_option('wet_proteinshake')
		);
		if ($_POST['wet_proteinshake-submit']) {
			$options['title'] = strip_tags(stripslashes($_POST['wet_proteinshake-title']));
			$options['buttontext'] = strip_tags(stripslashes($_POST['wet_proteinshake-buttontext']));
			$options['useounces'] = ($_POST['wet_proteinshake-useounces'] == 'us');
			$options['infohref'] = $_POST['wet_proteinshake-infohref'];

			update_option('wet_proteinshake', $options);
		}

		echo
		'<p style="text-align:right;"><label for="wet_proteinshake-title">' . __('Title:') .
		' <input style="width: 200px;" id="wet_proteinshake-title" name="wet_proteinshake-title" type="text" value="'.esc_html($options['title']).'" /></label></p>' .
		'<p style="text-align:right;"><label for="wet_proteinshake-buttontext">' .  __('Button Text:', 'wet_proteinshake') .
		' <input style="width: 200px;" id="wet_proteinshake-buttontext" name="wet_proteinshake-buttontext" type="text" value="'.esc_html($options['buttontext']).'" /></label></p>' .
		'<p style="text-align:right;"><label for="wet_proteinshake-infohref">' .  __('Credit link:', 'wet_proteinshake') .
		' <input style="width: 200px;" id="wet_proteinshake-infohref" name="wet_proteinshake-infohref" type="text" value="' .esc_url($options['infohref']). '" /></label></p>'.
		'<p style="text-align:right;"><label for="wet_proteinshake-useounces">' .  __('US units:', 'wet_proteinshake') .
		' <input style="width: 20px;" id="wet_proteinshake-useounces" name="wet_proteinshake-useounces" value="us" type="radio"'.($options['useounces'] ? ' checked="checked"' : '').' /></label></p>' .
		'<p style="text-align:right;"><label for="wet_proteinshake-usegrams">' .  __('Metric units:', 'wet_proteinshake') .
		' <input style="width: 20px;" id="wet_proteinshake-usegrams" name="wet_proteinshake-useounces" value="metric" type="radio"'.(!$options['useounces'] ? ' checked="checked"' : '').' /></label></p>' .
		'<input type="hidden" id="wet_proteinshake-submit" name="wet_proteinshake-submit" value="1" />';
	}

    function view($is_widget, $args=array()) {
    	if ($is_widget) extract($args);

    	// get widget options
    	$options = get_option('wet_proteinshake');
		$title = esc_js(esc_html($options['title']));
		$buttontext = esc_js(esc_html($options['buttontext']));
		$infohref = esc_url($options['infohref']);
    	$useounces = ($options['useounces'] ? '1' : '0');

    	// l10n strings
    	$lbl_weight = __('Weight', 'wet_proteinshake');
    	$lbl_carbo = __('Carbohydrate', 'wet_proteinshake');
    	$lbl_water = __('Water', 'wet_proteinshake');
        $lbl_protein = __('Protein', 'wet_proteinshake');
        $lbl_daily = __('Daily protein dose', 'wet_proteinshake');
        $dim_ing = ($useounces ? 'oz' : 'g');
        $dim_liquid = ($useounces ? 'oz' : 'ml');
        $dim_weight = ($useounces ? 'lbs' : 'kg');
        $default_weight = ($useounces ? '180.0' : '90.0');

        // all calculation is done by the client, trying to compensate for common errors like mixing meters with centimeters.
    	$point = __('.', 'wet_proteinshake'); // decimal point
    	$bs = '\\';

    	$out[] = <<<EOT
            <script type="text/javascript">
            function wet_proteinshake()
            {
            	var use_ounces = {$useounces};
            	var precision = 0;
            	var factor = 1;
            	var factor_liquid = 1;

            	var theform = document.getElementById('wet_proteinshake_form');
            	var protein = document.getElementById('wet_proteinshake_protein');
            	var carbo = document.getElementById('wet_proteinshake_carbo');
            	var water = document.getElementById('wet_proteinshake_water');
                var daily = document.getElementById('wet_proteinshake_daily');
            	var w = theform.wet_proteinshake_weight.value;
            	w = w.replace(/{$bs}{$point}/, ".");
            	if ( w  >= 0 ) {
            		if (use_ounces) {
            			precision = 2;
            			factor = 28.349523125; // ounces to gram
            			factor_liquid = 29.573529687517038 // ounces to ml
            			w = w * 0.45359237; // lbs to kg
            		}
            		protein.innerHTML = (w * 0.4 / factor).toFixed(precision).replace(/\./, "{$point}");
            		carbo.innerHTML = (w * 0.8  / factor).toFixed(precision).replace(/\./, "{$point}");
                	water.innerHTML = (w * 10.0 / factor_liquid).toFixed(precision).replace(/\./, "{$point}");
                	daily.innerHTML = (w * 2.2 / factor).toFixed(precision).replace(/\./, "{$point}");
    			} else {
            		protein.innerHTML =
            		carbo.innerHTML =
                	water.innerHTML =
                	daily.innerHTML = "-";
    }
            }
            </script>
EOT;
    	// the widget's form
		$out[] = $before_widget . $before_title . $title . $after_title;
		$out[] = '<div style="margin-top:5px;">';
        $out[] = '<noscript><p>'.__('This Widget requires Javascript', 'wet_proteinshake').'</p></noscript>';
		$out[] = <<<FORM
<style type="text/css">
#wet_proteinshake_form td {padding: 5px 0;}
#wet_proteinshake_form td strong {margin: 0 0 0 0.7em;}
#wet_proteinshake_form tr { border-bottom: 1px solid #eee; margin: 2px; }
#wet_proteinshake_form input[type="submit"] { margin-top: 0.5em; }
</style>
<form id='wet_proteinshake_form' method='post'>
	<table id='wet_proteinshake_pane'>
	<col class="label" />
	<col class="value" />
	<tr>
	<td><label for='wet_proteinshake_weight'>{$lbl_weight}</label></td>
	<td><input id='wet_proteinshake_weight' type='text' name='wet_proteinshake_weight' value="{$default_weight}" size='5' />&nbsp;{$dim_weight}</td>
	</tr>
	<tr><td>{$lbl_protein}</td><td><strong id='wet_proteinshake_protein'>-</strong>&nbsp;{$dim_ing}</td></tr>
	<tr><td>{$lbl_carbo}</td><td><strong id='wet_proteinshake_carbo'>-</strong>&nbsp;{$dim_ing}</td></tr>
	<tr><td>{$lbl_water}</td><td><strong id='wet_proteinshake_water'>-</strong>&nbsp;{$dim_liquid}</td></tr>
	<tr><td>{$lbl_daily}</td><td><strong id='wet_proteinshake_daily'>-</strong>&nbsp;{$dim_ing}</td></tr>
	</table>
	<p><input type='submit' value='{$buttontext}' onclick='wet_proteinshake(); return false;' /></p>
</form>
FORM;
		if (!empty($options['infohref'])) {
			$out[] = '<p style="text-align:right"><small>'.__('by').' <a href="'.$infohref.'">'.__('Whey Protein Institut').'</a></small></p>';
		}
		$out[] = '</div>';
		$out[] = $after_widget;
    	return join($out, "\n");
    }

    function shortcode($atts, $content=null) {
        return wet_proteinshake::view(false);
    }

    function widget($atts) {
        echo wet_proteinshake::view(true, $atts);
    }
}

if(function_exists('add_action')) {
	add_action('widgets_init', array('wet_proteinshake', 'init'));
} else {
	die('<!DOCTYPE html><html><head><title>Protein Shake Recipe Calculator | WordPress Plugin</title></head><body>Nothing here!</body></html>');
}

?>