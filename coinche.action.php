<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Coinche implementation : © Christophe Badoit <gameboardarena@tof2k.com>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 *
 * coinche.action.php
 *
 * Coinche main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/coinche/coinche/myAction.html", ...)
 *
 */

class action_coinche extends APP_GameAction {
	// Constructor: please do not modify
	public function __default() {
		if (self::isArg('notifwindow')) {
			$this->view = 'common_notifwindow';
			$this->viewArgs['table'] = self::getArg('table', AT_posint, true);
		} else {
			$this->view = 'coinche_coinche';
			self::trace('Complete reinitialization of board game');
		}
	}

	public function playCard() {
		self::setAjaxMode();
		$cardId = self::getArg('id', AT_posint, true);
		$wantBelote = self::getArg('belote', AT_bool, false);
		$this->game->playCard($cardId, $wantBelote);
		self::ajaxResponse();
	}

	public function bid() {
		self::setAjaxMode();
		$value = self::getArg('value', AT_posint, true);
		$color = self::getArg('color', AT_posint, true);
		$this->game->bid($color, $value);
		self::ajaxResponse();
	}

	public function pass() {
		self::setAjaxMode();
		$this->game->pass();
		self::ajaxResponse();
	}

	public function coinche() {
		self::setAjaxMode();
		$this->game->coinche();
		self::ajaxResponse();
	}

	public function nosurcoinche() {
		self::setAjaxMode();
		$this->game->nosurcoinche();
		self::ajaxResponse();
	}

	public function surcoinche() {
		self::setAjaxMode();
		$this->game->surcoinche();
		self::ajaxResponse();
	}

	/*
    
    Example:
  	
    public function myAction()
    {
        self::setAjaxMode();     

        // Retrieve arguments
        // Note: these arguments correspond to what has been sent through the javascript "ajaxcall" method
        $arg1 = self::getArg( "myArgument1", AT_posint, true );
        $arg2 = self::getArg( "myArgument2", AT_posint, true );

        // Then, call the appropriate method in your game logic, like "playCard" or "myAction"
        $this->game->myAction( $arg1, $arg2 );

        self::ajaxResponse( );
    }
    
    */
}
