<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BeloteCoinche implementation : © Christophe Badoit <gameboardarena@tof2k.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

require_once APP_BASE_PATH . 'view/common/game.view.php';

class view_belotecoinche_belotecoinche extends game_view {
	function getGameName() {
		return 'belotecoinche';
	}

	function build_page($viewArgs) {
		// Get players & players number
		$players = $this->game->loadPlayersBasicInfos();
		$players_nbr = count($players);

		/*********** Place your code below:  ************/
		$template = self::getGameName() . '_' . self::getGameName();

		$directions = array('S', 'W', 'N', 'E');

		// this will inflate our player block with actual players data
		$this->page->begin_block($template, 'player');
		foreach ($players as $player) {
			$dir = array_shift($directions);
			$this->page->insert_block('player', array(
				'PLAYER_ID' => $player['player_id'],
				'PLAYER_NAME' => $player['player_name'],
				'PLAYER_COLOR' => $player['player_color'],
				'DIR' => $dir,
			));
		}
		// this will make our My Hand text translatable
		$this->tpl['MY_HAND'] = self::_('My hand');

		/*
        
        // Examples: set the value of some element defined in your tpl file like this: {MY_VARIABLE_ELEMENT}

        // Display a specific number / string
        $this->tpl['MY_VARIABLE_ELEMENT'] = $number_to_display;

        // Display a string to be translated in all languages: 
        $this->tpl['MY_VARIABLE_ELEMENT'] = self::_("A string to be translated");

        // Display some HTML content of your own:
        $this->tpl['MY_VARIABLE_ELEMENT'] = self::raw( $some_html_code );
        
        */

		/*
        
        // Example: display a specific HTML block for each player in this game.
        // (note: the block is defined in your .tpl file like this:
        //      <!-- BEGIN myblock --> 
        //          ... my HTML code ...
        //      <!-- END myblock --> 
        

        $this->page->begin_block( "belotecoinche_belotecoinche", "myblock" );
        foreach( $players as $player )
        {
            $this->page->insert_block( "myblock", array( 
                                                    "PLAYER_NAME" => $player['player_name'],
                                                    "SOME_VARIABLE" => $some_value
                                                    ...
                                                     ) );
        }
        
        */

		/*********** Do not change anything below this line  ************/
	}
}
