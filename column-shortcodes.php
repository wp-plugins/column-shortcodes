<?php
/*
Plugin Name: 		Column Shortcodes
Version: 			0.2
Description: 		Adds shortcodes to easily create columns in your posts or pages
Author: 			Codepress
Author URI: 		http://www.codepress.nl
Plugin URI: 		http://www.codepress.nl/plugins/
Text Domain: 		column-shortcodes
Domain Path: 		/languages
License:			GPLv2

Copyright 2011  Codepress  info@codepress.nl

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2 as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'CPSH_VERSION', 	'0.1' );
define( 'CPSH_URL', 		plugins_url('', __FILE__) );
define( 'CPSH_TEXTDOMAIN', 	'column-shortcodes' );

// Long posts should require a higher limit, see http://core.trac.wordpress.org/ticket/8553
@ini_set('pcre.backtrack_limit', 500000);

/**
 * Column Shortcodes
 *
 * @since     0.1
 *
 */
class Codepress_Column_Shortcodes
{		
	/**
	 * Constructor
	 *
	 * @since     0.1
	 */
	function __construct()
	{
		add_action( 'wp_loaded', array( $this, 'init') );
	}
	
	/**
	 * Initialize plugin.	 
	 *
	 * @since     0.1
	 */
	public function init()
	{
		$this->add_shortcodes();
		
		add_action('admin_init', array( $this, 'add_editor_buttons' ) );
		add_action( 'admin_footer', array( $this, 'popup' ) );
		
		// styling
		add_action( 'admin_print_styles', array( $this, 'admin_styles') );		
		add_action( 'wp_enqueue_scripts',  array( $this, 'frontend_styles') );
		
		// scripts, only load when editor is available
		add_action( 'tiny_mce_plugins', array( $this, 'admin_scripts') );
		
		// translations
		load_plugin_textdomain( CPSH_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
	
	/**
	 * Register admin css
	 *
	 * @since     0.1
	 */
	public function admin_styles()
	{
		wp_enqueue_style( 'cpsh-admin', CPSH_URL.'/assets/css/admin.css', array(), CPSH_VERSION, 'all' );	
	}
	
	/**
	 * Register admin scripts
	 *
	 * @since     0.1
	 */
	public function admin_scripts( $plugins ) 
	{		
		wp_enqueue_script( 'cpsh-admin', CPSH_URL.'/assets/js/admin.js', array('jquery'), CPSH_VERSION );
		
		return $plugins;
	}	
		
	/**
	 * Register frontend styles
	 *
	 * @since     1.0
	 */
	public function frontend_styles()
	{	
		wp_enqueue_style( 'cpsh-shortcodes', CPSH_URL.'/assets/css/shortcodes.css', array(), CPSH_VERSION, 'all' );
	}
	
	/**
	 * Add shortcodes
	 *
	 * @since     0.1
	 */
	function add_shortcodes() 
	{
		foreach ( $this->get_shortcodes() as $shortcode ) {
			add_shortcode($shortcode['name'], array( $this, 'columns') );
		}		
	}
	
	/**
	 * Insert Markup
	 *
	 * @since     0.1
	 */
	function columns($atts, $content = null, $name='') 
	{
		extract(shortcode_atts(array(
			"id" 	=> '',
			"class" => ''
		), $atts));
		
		$content = $this->content_helper($content);
		
		$id = ($id <> '') ? " id='" . esc_attr( $id ) . "'" : '';
		$class = ($class <> '') ? esc_attr( ' ' . $class ) : '';
		
		$pos = strpos($name,'_last');	

		if($pos !== false)
			$name = str_replace('_last',' last_column',$name);
		
		$output = "<div{$id} class='{$name}{$class}'>{$content}</div>";
		
		if($pos !== false) 
			$output .= "<div class='clear_column'></div>";
		
		return $output;
	}
	
	/**
	 * Add buttons to TimyMCE
	 *
	 * @since     0.1
	 */
	function add_editor_buttons()
	{
		global $pagenow;
		
		if ( current_user_can( 'edit_posts' ) && current_user_can( 'edit_pages' ) && in_array($pagenow, array('post-new.php', 'page-new.php', 'post.php', 'page.php', 'profile.php', 'user-edit.php', 'user-new.php') ) ) {
				
			// add html buttons, when using this filter
			if( apply_filters('add_shortcode_html_buttons', false ) ) {
				add_action( 'admin_head', array( $this, 'add_html_buttons' ) );
			}
			
			// add shortcode button
			add_action( 'media_buttons', array( $this, 'add_shortcode_button' ), 100 );
		}		
	}
	
	/**
	 * Add shortcode button to TimyMCE
	 *
	 * @since     0.1
	 */
	function add_shortcode_button( $page = null, $target = null ) 
	{
		echo "
			<a href='#TB_inline?width=640&height=600&inlineId=cpsh-wrap' class='thickbox' title='" . __( 'Select shortcode', CPSH_TEXTDOMAIN ) . "' data-page='{$page}' data-target='{$target}'>
				<img src='" . CPSH_URL . "/assets/images/shortcode.png' alt='' />
			</a>
		";
	}	
	
	/**
	 * TB window Popup
	 *
	 * @since     0.1
	 */
	function popup() 
	{	
		$buttons = $this->get_shortcodes();
		
		// buttons
		$select = '';
		foreach ( $buttons as $button ) {
			
			$open_tag 	= str_replace('\n', '', $button['options']['open_tag']);
			$close_tag 	= str_replace('\n', '', $button['options']['close_tag']);
						
			$select .= "
				<a href='javascript:;' rel='{$open_tag}{$close_tag}' class='cp-{$button['name']} columns insert-shortcode'>
					{$button['options']['display_name']}
				</a>";
		}
	
		// popup frame
		$popup = "
		<div id='cpsh-wrap' style='display:none'>
			<div id='cpsh'>
				<div id='cpsh-generator-shell'>
					<div id='cpsh-generator-header'>
						<h2 class='cpsh-title'>" . __('Column shortcodes', CPSH_TEXTDOMAIN) . "</h2>
						{$select}
					</div>
					<div id='cpsh-settings'></div>					
				</div>
			</div>
		</div>
		";
		
		echo $popup;
	}
	
	/**
	 * get shortcodes
	 *
	 * @since     0.1
	 */
	function get_shortcodes()
	{
		$shortcodes = array();
		
		// define column shortcodes
		$column_shortcodes = array(
			'one_half' 		=> array ('display_name' => __('one half', CPSH_TEXTDOMAIN) ),
			'one_third' 	=> array ('display_name' => __('one third', CPSH_TEXTDOMAIN) ),
			'one_fourth' 	=> array ('display_name' => __('one fourth', CPSH_TEXTDOMAIN) ),
			'two_third' 	=> array ('display_name' => __('two third', CPSH_TEXTDOMAIN) ),
			'three_fourth' 	=> array ('display_name' => __('three fourth', CPSH_TEXTDOMAIN) ),
			'one_fifth' 	=> array ('display_name' => __('one fifth', CPSH_TEXTDOMAIN) ),
			'two_fifth' 	=> array ('display_name' => __('two fifth', CPSH_TEXTDOMAIN) ),
			'three_fifth' 	=> array ('display_name' => __('three fifth', CPSH_TEXTDOMAIN) ),
			'one_sixth' 	=> array ('display_name' => __('one sixth', CPSH_TEXTDOMAIN) )
		);
		
		foreach ( $column_shortcodes as $shortcode => $options ) {
			$shortcodes[] =	array(
				'name' 		=> $shortcode,
				'options' 	=> array(
					'display_name' 	=> $options['display_name'],
					'open_tag' 		=> '\n'."[{$shortcode}]",
					'close_tag' 	=> "[/{$shortcode}]".'\n',
					'key' 			=> ''
				)
			);
			$shortcodes[] =	array(
				'name' 		=> "{$shortcode}_last",
				'options' 	=> array(
					'display_name' 	=> $options['display_name'] . ' (' . __('last', CPSH_TEXTDOMAIN) . ')',
					'open_tag' 		=> '\n'."[{$shortcode}_last]",
					'close_tag' 	=> "[/{$shortcode}_last]".'\n',
					'key' 			=> ''
				)
			);
		}	
		
		return $shortcodes;
	}
	
	/**
	 * Add buttons to TimyMCE HTML tab
	 *
	 * @since     0.1
	 */
	function add_html_buttons()
	{		
		wp_print_scripts( 'quicktags' );								
		
		$shortcodes = $this->get_shortcodes();
				
		// output script
		$script = '';
		foreach ( $shortcodes as $shortcode ) {
			$options = $shortcode['options'];
			
			$script .= "edButtons[edButtons.length] = new edButton('ed_{$shortcode['name']}'
				,'{$shortcode['name']}'
				,'{$options['open_tag']}'
				,'{$options['close_tag']}'
				,'{$options['key']}'
			); \n";
		}
		
		$script = "
			<script type='text/javascript'>\n
				/* <![CDATA[ */ \n
				{$script}		
				\n /* ]]> */ \n
			</script>
		";
		
		echo $script;
	}
	
	/**
	 * Content Helper
	 *
	 * @since     0.1
	 */
	 function content_helper( $content, $paragraph_tag = false, $br_tag = false )
	{		
		$content = preg_replace('#^<\/p>|^<br \/>|<p>$#', '', $content);
		
		if ( $br_tag ) {
			$content = preg_replace('#<br \/>#', '', $content);
		}
		
		if ( $paragraph_tag ) {
			$content = preg_replace('#<p>|</p>#', '', $content);
		}
		
		return do_shortcode( shortcode_unautop( trim($content) ) );
	}
}

new Codepress_Column_Shortcodes();

?>