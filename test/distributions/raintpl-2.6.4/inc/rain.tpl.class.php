<?php

/**
 *  RainTPL
 *  -------
 *	Realized by Federico Ulfo & maintained by the Rain Team
 *	Distributed under GNU/LGPL 3 License
 *
 *  @version 2.6.4
 */


class RainTPL{

	// -------------------------
	// 	CONFIGURATION
	// -------------------------

		/**
		 * Template directory
		 *
		 * @var string
		 */
		static $tpl_dir = "tpl/";


		/**
		 * Cache directory. Is the directory where RainTPL will compile the template and save the cache
		 *
		 * @var string
		 */
		static $cache_dir = "tmp/";


		/**
		 * Template base URL. RainTPL will add this URL to the relative paths of element selected in $path_replace_list.
		 *
		 * @var string
		 */
		static $base_url = null;


		/**
		 * Template extension.
		 *
		 * @var string
		 */
		static $tpl_ext = "html";


		/**
		 * Path replace is a cool features that replace all relative paths of images (<img src="...">), stylesheet (<link href="...">), script (<script src="...">) and link (<a href="...">)
		 * Set true to enable the path replace.
		 *
		 * @var unknown_type
		 */
		static $path_replace = true;


		/**
		 * You can set what the path_replace method will replace.
		 * Avaible options: a, img, link, script, input
		 *
		 * @var array
		 */
		static $path_replace_list = array( 'a', 'img', 'link', 'script', 'input' );


		/**
		 * You can define in the black list what string are disabled into the template tags
		 *
		 * @var unknown_type
		 */
		static $black_list = array( '\$this', 'raintpl::', 'self::', '_SESSION', '_SERVER', '_ENV',  'eval', 'exec', 'unlink', 'rmdir' );


		/**
		 * Check template.
		 * true: checks template update time, if changed it compile them
		 * false: loads the compiled template. Set false if server doesn't have write permission for cache_directory.
		 *
		 */
		static $check_template_update = true;

	// -------------------------


	// -------------------------
	// 	RAINTPL VARIABLES
	// -------------------------

		/**
		 * Is the array where RainTPL keep the variables assigned
		 *
		 * @var array
		 */
		public $var = array();

		private $tpl = array(),		// variables to keep the template directories and info
			$cache = false,		// static cache enabled / disabled
                        $cache_id = null;       // identify only one cache

	// -------------------------



	const CACHE_EXPIRE_TIME = 3600; // default cache expire time = hour



	/**
	 * Assign variable
	 * eg. 	$t->assign('name','mickey');
	 *
	 * @param mixed $variable_name Name of template variable or associative array name/value
	 * @param mixed $value value assigned to this variable. Not set if variable_name is an associative array
	 */

	function assign( $variable, $value = null ){
		if( is_array( $variable ) )
			$this->var += $variable;
		else
			$this->var[ $variable ] = $value;
	}



	/**
	 * Draw the template
	 * eg. 	$html = $tpl->draw( 'demo', TRUE ); // return template in string
	 * or 	$tpl->draw( $tpl_name ); // echo the template
	 *
	 * @param string $tpl_name  template to load
	 * @param boolean $return_string  true=return a string, false=echo the template
	 * @return string
	 */

	function draw( $tpl_name, $return_string = false ){

		// compile the template if necessary and set the template filepath
		$this->check_template( $tpl_name );


		// Cache is off and, return_string is false
                // Rain just echo the template

                if( !$this->cache && !$return_string ){
                        extract( $this->var );
            		include $this->tpl['compiled_filename'];
                        unset( $this->tpl );
                }


		// cache or return_string are enabled
                // rain get the output buffer to save the output in the cache or to return it as string

                else{

                        //----------------------
                        // get the output buffer
                        //----------------------
			ob_start();
			extract( $this->var );
			include $this->tpl['compiled_filename'];
			$raintpl_contents = ob_get_contents();
			ob_end_clean();
                        //----------------------


                        // save the output in the cache
			if( $this->cache )
				file_put_contents( $this->tpl['cache_filename'], "<?php if(!class_exists('raintpl')){exit;}?>" . $raintpl_contents );

                        // free memory
                        unset( $this->tpl );

                        // return or print the template
                        if( $return_string ) return $raintpl_contents; else echo $raintpl_contents;

                }

	}




	/**
	 * If exists a valid cache for this template it returns the cache
	 *
	 * @param string $tpl_name Name of template (set the same of draw)
	 * @param int $expiration_time Set after how many seconds the cache expire and must be regenerated
	 * @return string it return the HTML or null if the cache must be recreated
	 */

	function cache( $tpl_name, $expire_time = self::CACHE_EXPIRE_TIME, $cache_id = null ){

                // set the cache_id
                $this->cache_id = $cache_id;

		if( !$this->check_template( $tpl_name ) && file_exists( $this->tpl['cache_filename'] ) && ( time() - filemtime( $this->tpl['cache_filename'] ) < $expire_time ) )
			return substr( file_get_contents( $this->tpl['cache_filename'] ), 43 );
		else{
			//delete the cache of the selected template
                        if (file_exists($this->tpl['cache_filename']))
                            unlink($this->tpl['cache_filename'] );
			$this->cache = true;
		}
	}



	/**
	 * Configure the settings of RainTPL
	 *
	 */
	static function configure( $setting, $value = null ){
		if( is_array( $setting ) )
			foreach( $setting as $key => $value )
				self::configure( $key, $value );
		else if( property_exists( __CLASS__, $setting ) )
			self::$$setting = $value;
	}



	// check if has to compile the template
	// return true if the template has changed
	private function check_template( $tpl_name ){

		if( !isset($this->tpl['checked']) ){

			$tpl_basename                       = basename( $tpl_name );														// template basename
			$tpl_basedir                        = strpos($tpl_name,"/") ? dirname($tpl_name) . '/' : null;						// template basedirectory
			$tpl_dir                            = self::$tpl_dir . $tpl_basedir;								// template directory
			$this->tpl['tpl_filename']          = $tpl_dir . $tpl_basename . '.' . self::$tpl_ext;	// template filename
			$temp_compiled_filename             = self::$cache_dir . md5( $this->tpl['tpl_filename'] );
			$this->tpl['compiled_filename']     = $temp_compiled_filename . '.php';	// cache filename
			$this->tpl['cache_filename']        = $temp_compiled_filename . '.s_' . $this->cache_id . '.php';	// static cache filename

			// if the template doesn't exsist throw an error
			if( self::$check_template_update && !file_exists( $this->tpl['tpl_filename'] ) ){
				trigger_error( 'Template <b>'.$this->tpl['tpl_filename'].'</b> not found!' );
				return '<div style="background:#f8f8ff;border:1px solid #aaaaff;padding:10px;">Template <b>'.$this->tpl['tpl_filename'].'</b> not found</div>';
			}

			// file doesn't exsist, or the template was updated, Rain will compile the template
			if( !file_exists( $this->tpl['compiled_filename'] ) || ( self::$check_template_update && filemtime($this->tpl['compiled_filename']) < filemtime( $this->tpl['tpl_filename'] ) ) ){
				$this->compileFile( $tpl_basename, $tpl_basedir, $this->tpl['tpl_filename'], self::$cache_dir, $this->tpl['compiled_filename'] );
				return true;
			}
			$this->tpl['checked'] = true;
		}
	}


	/**
	* execute stripslaches() on the xml block. Invoqued by preg_replace_callback function below
	* @access private
	*/
	private function xml_reSubstitution($capture) {
    		return "<?php echo '<?xml ".stripslashes($capture[1])." ?>'; ?>";
	} 

	/**
	 * Compile and write the compiled template file
	 * @access private
	 */
	private function compileFile( $tpl_basename, $tpl_basedir, $tpl_filename, $cache_dir, $compiled_filename ){

		//read template file
		$this->tpl['source'] = $template_code = file_get_contents( $tpl_filename );

		//xml substitution
		$template_code = preg_replace( "/<\?xml(.*?)\?>/s", "##XML\\1XML##", $template_code );

		//disable php tag
		$template_code = str_replace( array("<?","?>"), array("&lt;?","?&gt;"), $template_code );

		//xml re-substitution
		$template_code = preg_replace_callback ( "/##XML(.*?)XML##/s", array($this, 'xml_reSubstitution'), $template_code ); 

		//compile template
		$template_compiled = "<?php if(!class_exists('raintpl')){exit;}?>" . $this->compileTemplate( $template_code, $tpl_basedir );
		

		// fix the php-eating-newline-after-closing-tag-problem
		$template_compiled = str_replace( "?>\n", "?>\n\n", $template_compiled );

		// create directories
		if( !is_dir( $cache_dir ) )
			mkdir( $cache_dir, 0755, true );

		if( !is_writable( $cache_dir ) )
			die( "Cache directory <b>$cache_dir</b> doesn't have write permission. Set write permission or set RAINTPL_CHECK_TEMPLATE_UPDATE to false. More details on <a target=_blank href=http://www.raintpl.com/Documentation/Documentation-for-PHP-developers/Configuration/>Configuration</a>");

		//write compiled file
		file_put_contents( $compiled_filename, $template_compiled );
	}



	/**
	 * Compile template
	 * @access private
	 */
	private function compileTemplate( $template_code, $tpl_basedir ){

		//tag list
		$tag_regexp = array( 	'loop'	 	=> '(\{loop(?: name){0,1}="\${0,1}[^"]*"\})',
					'loop_close'	=> '(\{\/loop\})',
					'if'		=> '(\{if(?: condition){0,1}="[^"]*"\})',
					'elseif'	=> '(\{elseif(?: condition){0,1}="[^"]*"\})',
					'else'		=> '(\{else\})',
					'if_close'	=> '(\{\/if\})',
					'function'	=> '(\{function="[^"]*"\})',
					'noparse'	=> '(\{noparse\})',
					'noparse_close' => '(\{\/noparse\})',
					'ignore'	=> '(\{ignore\})',
					'ignore_close'	=> '(\{\/ignore\})',
					'include'	=> '(\{include="[^"]*"(?: cache="[^"]*")?\})',
					'template_info'	=> '(\{\$template_info\})',
							);

		$tag_regexp = "/" . join( "|", $tag_regexp ) . "/";

		//split the code with the tags regexp
		$template_code = preg_split ( $tag_regexp, $template_code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

		//path replace (src of img, background and href of link)
		$template_code = $this->path_replace( $template_code, $tpl_basedir );

		//compile the code
		$compiled_code = $this->compileCode( $template_code );

		//return the compiled code
		return $compiled_code;

	}



	/**
	 * Compile the code
	 * @access private
	 */
	private function compileCode( $parsed_code ){

		//variables initialization
		$compiled_code = $open_if = $comment_is_open = $ignore_is_open = null;
                $loop_level = 0;


	 	//read all parsed code
	 	while( $html = array_shift( $parsed_code ) ){

	 		//close ignore tag
			if( !$comment_is_open && strpos( $html, '{/ignore}' ) !== FALSE )
	 			$ignore_is_open = false;

	 		//code between tag ignore id deleted
	 		elseif( $ignore_is_open ){
	 			//ignore the code
	 		}

	 		//close no parse tag
			elseif( strpos( $html, '{/noparse}' ) !== FALSE )
	 			$comment_is_open = false;

	 		//code between tag noparse is not compiled
	 		elseif( $comment_is_open )
 				$compiled_code .= $html;

	 		//ignore
			elseif( strpos( $html, '{ignore}' ) !== FALSE )
	 			$ignore_is_open = true;

	 		//noparse
	 		elseif( strpos( $html, '{noparse}' ) !== FALSE )
	 			$comment_is_open = true;

			//include tag
			elseif( preg_match( '/(?:\{include="([^"]*)"(?: cache="([^"]*)"){0,1}\})/', $html, $code ) ){

				//variables substitution
				$include_var = $this->var_replace( $code[ 1 ], $left_delimiter = null, $right_delimiter = null, $php_left_delimiter = '".' , $php_right_delimiter = '."', $loop_level );

				// if the cache is active
				if( isset($code[ 2 ]) )
					//dynamic include
					$compiled_code .= '<?php $tpl = new RainTPL;' .
								 'if( $cache = $tpl->cache( $template = basename("'.$include_var.'") ) )' .
								 '	echo $cache;' .
								 'else{ ' .
								 '$tpl_dir_temp = self::$tpl_dir;' .
								 '$tpl->assign( $this->var );' .
								 'self::$tpl_dir .= dirname("'.$include_var.'") . ( substr("'.$include_var.'",-1,1) != "/" ? "/" : "" );' .
								 ( !$loop_level ? null : '$tpl->assign( "key", $key'.$loop_level.' ); $tpl->assign( "value", $value'.$loop_level.' );' ).
								 '$tpl->draw( $template );'.
								 'self::$tpl_dir = $tpl_dir_temp;' .
								 '}' .
								 '?>';
				else
					//dynamic include
					$compiled_code .= '<?php $tpl = new RainTPL;' .
								 '$tpl_dir_temp = self::$tpl_dir;' .
								 '$tpl->assign( $this->var );' .
								 'self::$tpl_dir .= dirname("'.$include_var.'") . ( substr("'.$include_var.'",-1,1) != "/" ? "/" : "" );' .
								 ( !$loop_level ? null : '$tpl->assign( "key", $key'.$loop_level.' ); $tpl->assign( "value", $value'.$loop_level.' );' ).
								 '$tpl->draw( basename("'.$include_var.'") );'.
								 'self::$tpl_dir = $tpl_dir_temp;' .
								 '?>';

			}

	 		//loop
			elseif( preg_match( '/\{loop(?: name){0,1}="\${0,1}([^"]*)"\}/', $html, $code ) ){

	 			//increase the loop counter
	 			$loop_level++;

				//replace the variable in the loop
				$var = $this->var_replace( '$' . $code[ 1 ], $tag_left_delimiter=null, $tag_right_delimiter=null, $php_left_delimiter=null, $php_right_delimiter=null, $loop_level-1 );

				//loop variables
				$counter = "\$counter$loop_level";       // count iteration
				$key = "\$key$loop_level";               // key
				$value = "\$value$loop_level";           // value

				//loop code
				$compiled_code .=  "<?php $counter=-1; if( isset($var) && is_array($var) && sizeof($var) ) foreach( $var as $key => $value ){ $counter++; ?>";

			}

			//close loop tag
			elseif( strpos( $html, '{/loop}' ) !== FALSE ) {

				//iterator
				$counter = "\$counter$loop_level";

				//decrease the loop counter
				$loop_level--;

				//close loop code
				$compiled_code .=  "<?php } ?>";

			}

			//if
			elseif( preg_match( '/\{if(?: condition){0,1}="([^"]*)"\}/', $html, $code ) ){

				//increase open if counter (for intendation)
				$open_if++;

				//tag
				$tag = $code[ 0 ];

				//condition attribute
				$condition = $code[ 1 ];

				// check if there's any function disabled by black_list
				$this->function_check( $tag );

				//variable substitution into condition (no delimiter into the condition)
				$parsed_condition = $this->var_replace( $condition, $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level );

				//if code
				$compiled_code .=   "<?php if( $parsed_condition ){ ?>";

			}

			//elseif
			elseif( preg_match( '/\{elseif(?: condition){0,1}="([^"]*)"\}/', $html, $code ) ){

				//tag
				$tag = $code[ 0 ];

				//condition attribute
				$condition = $code[ 1 ];

				//variable substitution into condition (no delimiter into the condition)
				$parsed_condition = $this->var_replace( $condition, $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level );

				//elseif code
				$compiled_code .=   "<?php }elseif( $parsed_condition ){ ?>";
			}

			//else
			elseif( strpos( $html, '{else}' ) !== FALSE ) {

				//else code
				$compiled_code .=   '<?php }else{ ?>';

			}

			//close if tag
			elseif( strpos( $html, '{/if}' ) !== FALSE ) {

				//decrease if counter
				$open_if--;

				// close if code
				$compiled_code .=   '<?php } ?>';

			}

			//function
			elseif( preg_match( '/\{function="([^(]*)(\([^)]*\)){0,1}"\}/', $html, $code ) ){

				//tag
				$tag = $code[ 0 ];

				//function
				$function = $code[ 1 ];

				// check if there's any function disabled by black_list
				$this->function_check( $tag );

				//parse the parameters
				$parsed_param = isset( $code[2] ) ? $this->var_replace( $code[2], $tag_left_delimiter = null, $tag_right_delimiter = null, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level ) : '()';

				//if code
				$compiled_code .=   "<?php echo {$function}{$parsed_param}; ?>";
			}

			// show all vars
			elseif ( strpos( $html, '{$template_info}' ) !== FALSE ) {

				//tag
				$tag  = '{$template_info}';

				//if code
				$compiled_code .=   '<?php echo "<pre>"; print_r( $this->var ); echo "</pre>"; ?>';
			}


			//all html code
			else{

				//variables substitution (es. {$title})
				$html = $this->var_replace( $html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '<?php ', $php_right_delimiter = ';?>', $loop_level, $echo = true );
				//const substitution (es. {#CONST#})
				$html = $this->const_replace( $html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '<?php ', $php_right_delimiter = ';?>', $loop_level, $echo = true );
				//functions substitution (es. {"string"|functions})
				$compiled_code .= $this->func_replace( $html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '<?php ', $php_right_delimiter = ';?>', $loop_level, $echo = true );
			}
		}

		if( $open_if > 0 )
			die( "Error! You need to close an {if} tag in <b>". $this->tpl['tpl_filename'] ." </b>template" );

		return $compiled_code;
	}



	/**
	 * replace the path of image src, link href and a href.
	 * url => template_dir/url
	 * url# => url
	 * http://url => http://url
	 *
	 * @param string $html
	 * @return string html sostituito
	 */
	private function path_replace( $html, $tpl_basedir ){

		if( self::$path_replace ){

                        // reduce the path
                        $path = preg_replace('/\w+\/\.\.\//', '', self::$base_url . self::$tpl_dir . $tpl_basedir );

			$exp = $sub = array();

			if( in_array( "img", self::$path_replace_list ) ){
				$exp = array( '/<img(.*?)src=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<img(.*?)src=(?:")([^"]+?)#(?:")/i', '/<img(.*?)src="(.*?)"/', '/<img(.*?)src=(?:\@)([^"]+?)(?:\@)/i' );
				$sub = array( '<img$1src=@$2://$3@', '<img$1src=@$2@', '<img$1src="' . self::$base_url . self::$tpl_dir . $tpl_basedir . '$2"', '<img$1src="$2"' );
			}

			if( in_array( "script", self::$path_replace_list ) ){
				$exp = array_merge( $exp , array( '/<script(.*?)src=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<script(.*?)src=(?:")([^"]+?)#(?:")/i', '/<script(.*?)src="(.*?)"/', '/<script(.*?)src=(?:\@)([^"]+?)(?:\@)/i' ) );
				$sub = array_merge( $sub , array( '<script$1src=@$2://$3@', '<script$1src=@$2@', '<script$1src="' . self::$base_url . self::$tpl_dir . $tpl_basedir . '$2"', '<script$1src="$2"' ) );
			}

			if( in_array( "link", self::$path_replace_list ) ){
				$exp = array_merge( $exp , array( '/<link(.*?)href=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<link(.*?)href=(?:")([^"]+?)#(?:")/i', '/<link(.*?)href="(.*?)"/', '/<link(.*?)href=(?:\@)([^"]+?)(?:\@)/i' ) );
				$sub = array_merge( $sub , array( '<link$1href=@$2://$3@', '<link$1href=@$2@' , '<link$1href="' . self::$base_url . self::$tpl_dir . $tpl_basedir . '$2"', '<link$1href="$2"' ) );
			}

			if( in_array( "a", self::$path_replace_list ) ){
				$exp = array_merge( $exp , array( '/<a(.*?)href=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<a(.*?)href="(.*?)"/', '/<a(.*?)href=(?:\@)([^"]+?)(?:\@)/i'  ) );
				$sub = array_merge( $sub , array( '<a$1href=@$2://$3@', '<a$1href="' . self::$base_url . '$2"', '<a$1href="$2"' ) );
			}

			if( in_array( "input", self::$path_replace_list ) ){
				$exp = array_merge( $exp , array( '/<input(.*?)src=(?:")(http|https)\:\/\/([^"]+?)(?:")/i', '/<input(.*?)src=(?:")([^"]+?)#(?:")/i', '/<input(.*?)src="(.*?)"/', '/<input(.*?)src=(?:\@)([^"]+?)(?:\@)/i' ) );
				$sub = array_merge( $sub , array( '<input$1src=@$2://$3@', '<input$1src=@$2@', '<input$1src="' . self::$base_url . self::$tpl_dir . $tpl_basedir . '$2"', '<input$1src="$2"' ) );
			}

			return preg_replace( $exp, $sub, $html );

		}
		else
			return $html;

	}





	// replace const
	function const_replace( $html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level = null, $echo = null ){
		// const
		return preg_replace( '/\{\#(\w+)\#{0,1}\}/', $php_left_delimiter . ( $echo ? " echo " : null ) . '\\1' . $php_right_delimiter, $html );
	}



	// replace functions/modifiers on constants and strings
	function func_replace( $html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level = null, $echo = null ){

		preg_match_all( '/' . '\{\#{0,1}(\"{0,1}.*?\"{0,1})(\|\w.*?)\#{0,1}\}' . '/', $html, $matches );

		for( $i=0, $n=count($matches[0]); $i<$n; $i++ ){

			//complete tag ex: {$news.title|substr:0,100}
			$tag = $matches[ 0 ][ $i ];

			//variable name ex: news.title
			$var = $matches[ 1 ][ $i ];

			//function and parameters associate to the variable ex: substr:0,100
			$extra_var = $matches[ 2 ][ $i ];

			// check if there's any function disabled by black_list
			$this->function_check( $tag );

			$extra_var = $this->var_replace( $extra_var, null, null, null, null, $loop_level );

			// check if there's an operator = in the variable tags, if there's this is an initialization so it will not output any value
			$is_init_variable = preg_match( "/^(\s*?)\=[^=](.*?)$/", $extra_var );

			//function associate to variable
			$function_var = ( $extra_var and $extra_var[0] == '|') ? substr( $extra_var, 1 ) : null;

			//variable path split array (ex. $news.title o $news[title]) or object (ex. $news->title)
			$temp = preg_split( "/\.|\[|\-\>/", $var );

			//variable name
			$var_name = $temp[ 0 ];

			//variable path
			$variable_path = substr( $var, strlen( $var_name ) );

			//parentesis transform [ e ] in [" e in "]
			$variable_path = str_replace( '[', '["', $variable_path );
			$variable_path = str_replace( ']', '"]', $variable_path );

			//transform .$variable in ["$variable"]
			$variable_path = preg_replace('/\.\$(\w+)/', '["$\\1"]', $variable_path );

			//transform [variable] in ["variable"]
			$variable_path = preg_replace('/\.(\w+)/', '["\\1"]', $variable_path );

			//if there's a function
			if( $function_var ){


                                // check if there's a function or a static method and separate, function by parameters
				$function_var = str_replace("::", "@double_dot@", $function_var );

                                // get the position of the first :
                                if( $dot_position = strpos( $function_var, ":" ) ){

                                    // get the function and the parameters
                                    $function = substr( $function_var, 0, $dot_position );
                                    $params = substr( $function_var, $dot_position+1 );

                                }
                                else{

                                    //get the function
                                    $function = str_replace( "@double_dot@", "::", $function_var );
                                    $params = null;

                                }

                                // replace back the @double_dot@ with ::
                                $function = str_replace( "@double_dot@", "::", $function );
                                $params = str_replace( "@double_dot@", "::", $params );


			}
			else
				$function = $params = null;

			$php_var = $var_name . $variable_path;

			// compile the variable for php
			if( isset( $function ) ){
				if( $php_var )
					$php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? 'echo ' : null ) . ( $params ? "( $function( $php_var, $params ) )" : "$function( $php_var )" ) . $php_right_delimiter;
				else
					$php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? 'echo ' : null ) . ( $params ? "( $function( $params ) )" : "$function()" ) . $php_right_delimiter;
			}
			else
				$php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? 'echo ' : null ) . $php_var . $extra_var . $php_right_delimiter;

			$html = str_replace( $tag, $php_var, $html );

		}

		return $html;

	}



	function var_replace( $html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_level = null, $echo = null ){

		//all variables
		if( preg_match_all( '/' . $tag_left_delimiter . '\$(\w+(?:\.\${0,1}[A-Za-z0-9_]+)*(?:(?:\[\${0,1}[A-Za-z0-9_]+\])|(?:\-\>\${0,1}[A-Za-z0-9_]+))*)(.*?)' . $tag_right_delimiter . '/', $html, $matches ) ){

                    for( $parsed=array(), $i=0, $n=count($matches[0]); $i<$n; $i++ )
                        $parsed[$matches[0][$i]] = array('var'=>$matches[1][$i],'extra_var'=>$matches[2][$i]);

                    foreach( $parsed as $tag => $array ){

                            //variable name ex: news.title
                            $var = $array['var'];

                            //function and parameters associate to the variable ex: substr:0,100
                            $extra_var = $array['extra_var'];

                            // check if there's any function disabled by black_list
                            $this->function_check( $tag );

                            $extra_var = $this->var_replace( $extra_var, null, null, null, null, $loop_level );

                            // check if there's an operator = in the variable tags, if there's this is an initialization so it will not output any value
                            $is_init_variable = preg_match( "/^(\s*?)\=[^=](.*?)$/", $extra_var );

                            //function associate to variable
                            $function_var = ( $extra_var and $extra_var[0] == '|') ? substr( $extra_var, 1 ) : null;

                            //variable path split array (ex. $news.title o $news[title]) or object (ex. $news->title)
                            $temp = preg_split( "/\.|\[|\-\>/", $var );

                            //variable name
                            $var_name = $temp[ 0 ];

                            //variable path
                            $variable_path = substr( $var, strlen( $var_name ) );

                            //parentesis transform [ e ] in [" e in "]
                            $variable_path = str_replace( '[', '["', $variable_path );
                            $variable_path = str_replace( ']', '"]', $variable_path );

                            //transform .$variable in ["$variable"]
                            $variable_path = preg_replace('/\.\$(\w+)/', '["$\\1"]', $variable_path );

                            //transform [variable] in ["variable"]
                            $variable_path = preg_replace('/\.(\w+)/', '["\\1"]', $variable_path );

                            //if there's a function
                            if( $function_var ){

                                    //split function by function_name and parameters (ex substr:0,100)
                                    $function_split = explode( ':', $function_var, 2 );

                                    //function name
                                    $function = $function_split[ 0 ];

                                    //function parameters
                                    $params = ( isset( $function_split[ 1 ] ) ) ? $function_split[ 1 ] : null;

                            }
                            else
                                    $function = $params = null;

                            //if it is inside a loop
                            if( $loop_level ){
                                    //verify the variable name
                                    if( $var_name == 'key' )
                                            $php_var = '$key' . $loop_level;
                                    elseif( $var_name == 'value' )
                                            $php_var = '$value' . $loop_level . $variable_path;
                                    elseif( $var_name == 'counter' )
                                            $php_var = '$counter' . $loop_level;
                                    else
                                            $php_var = "\$" . $var_name . $variable_path;
                            }else
                                    $php_var = "\$" . $var_name . $variable_path;

                            // compile the variable for php
                            if( isset( $function ) )
                                    $php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? 'echo ' : null ) . ( $params ? "( $function( $php_var, $params ) )" : "$function( $php_var )" ) . $php_right_delimiter;
                            else
                                    $php_var = $php_left_delimiter . ( !$is_init_variable && $echo ? 'echo ' : null ) . $php_var . $extra_var . $php_right_delimiter;

                            $html = str_replace( $tag, $php_var, $html );


                    }
                }

		return $html;
	}



	/**
	 * Check if function is in black list (sandbox)
	 *
	 * @param string $code
	 * @param string $tag
	 */
	private function function_check( $code ){

		$preg = '#(\W|\s)' . implode( '(\W|\s)|(\W|\s)', self::$black_list ) . '(\W|\s)#';

		// check if the function is in the black list (or not in white list)
		if( count(self::$black_list) && preg_match( $preg, $code, $match ) ){

			// find the line of the error
			$line = 0;
			$rows=explode("\n",$this->tpl['source']);
			while( !strpos($rows[$line],$code) )
				$line++;

			// draw the error line
			$error = str_replace( array('<','>'), array( '&lt;','&gt;' ), array($code,$rows[$line]) );
			$error = str_replace( $code, "<font color=red>$code</font>", $rows[$line] );

			// debug the error and stop the execution of the script
			die( "<div>RainTPL Sandbox Error in template <b>{$this->tpl['tpl_filename']}</b> at line $line : <i>$error</i></b>" );
		}

	}

}




?>