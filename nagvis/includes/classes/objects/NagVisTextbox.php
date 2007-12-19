<?php
/**
 * Class of a Host in Nagios with all necessary informations
 */
class NagVisTextbox extends NagVisStatelessObject {
	var $MAINCFG;
	var $LANG;
	
	var $background_color;
	
	/**
	 * Class constructor
	 *
	 * @param		Object 		Object of class GlobalMainCfg
	 * @param		Object 		Object of class GlobalBackendMgmt
	 * @param		Object 		Object of class GlobalLanguage
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function NagVisTextbox(&$MAINCFG, &$LANG) {
		$this->MAINCFG = &$MAINCFG;
		$this->LANG = &$LANG;
		
		$this->class = 'box';
		$this->background_color = '#CCCCCC';
		
		$this->type = 'textbox';
		parent::NagVisStatelessObject($this->MAINCFG, $this->LANG);
	}
	
	/**
	 * PUBLIC parse()
	 *
	 * Parses the object
	 *
	 * @return	String		HTML code of the object
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function parse() {
		$this->replaceMacros();
		return $this->parseTextbox();
	}
	
	# End public methods
	# #########################################################################
	
	/**
	 * Replaces macros of urls and hover_urls
	 *
	 * @author 	Lars Michelsen <lars@vertical-visions.de>
	 */
	function replaceMacros() {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisMap::replaceMacros(&$obj)');
		
		$this->text = str_replace('[refresh_counter]','<font id="refreshCounter"></font>', $this->text);
		
		if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisMap::replaceMacros(): Array(...)');
		return TRUE;
	}
	
	/**
	 * Create a Comment-Textbox
	 *
	 * @return	String	String with HTML Code
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function parseTextbox() {
		if (DEBUG&&DEBUGLEVEL&1) debug('Start method NagVisTextbox::parseTextbox(&$obj)');
		$ret = '<div class="'.$this->class.'" style="background:'.$this->background_color.';left:'.$this->x.'px;top:'.$this->y.'px;width:'.$this->w.'px;overflow:visible;">';	
		$ret .= "\t".'<span>'.$this->text.'</span>';
		$ret .= '</div>';
		if (DEBUG&&DEBUGLEVEL&1) debug('End method NagVisTextbox::parseTextbox(): Array(...)');
		return $ret;	
	}
	
	/**
	 * Just a dummy here (Textbox won't need an icon)
	 *
	 * @author	Lars Michelsen <lars@vertical-visions.de>
	 */
	function fetchIcon() {
		//FIXME: Nothing to do here, icon is set in constructor
	}
}
?>
