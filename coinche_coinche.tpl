{OVERALL_GAME_HEADER}

<!--
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- BeloteCoinche implementation : © Christophe Badoit <gameboardarena@tof2k.com>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

-->

<div class="playerTables">

	<div class="playerTables__wrapper">

		<!-- BEGIN player -->
		<div class="playerTables__table playerTables__table--{DIR} playerTables__table--id--{PLAYER_ID}">
			<div class="playerTables__card" id="playerTables__card--{PLAYER_ID}"></div>
			<div class="playerTables__status"></div>
			<div class="playerTables__avatar-wrapper">
				<div class="playerTables__table__firstMarker"></div>
				<div class="playerTables__table__counterMarker">C</div>
				<div class="playerTables__avatar" style="background-image: url({PLAYER_AVATAR_URL_184})"></div>
				<div class="playerTables__bubble"></div>
				<div class="playerTables__name" style="background-color:#{PLAYER_COLOR}b0"><span>{PLAYER_NAME}</span></div>
			</div>
		</div>
		<!-- END player -->

		<div class="playerTables__coinche-btn">
			<a class="bgabutton bgabutton_red bgabutton_big">{COUNTER} !</a>
		</div>

		<div class="bidPanel">

			<div class="bidPanel__title">
				{BID_OR} <a class="bidPanel__btn bidPanel__btn--pass action-button bgabutton bgabutton_blue">{PASS}</a>
			</div>

			<div class="bidPanel__values">
				<a class="bidPanel__btn bidPanel__btn--value" data-value="82">82</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="90">90</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="100">100</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="110">110</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="120">120</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="130">130</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="140">140</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="150">150</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="160">160</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="170">170</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="180">180</a>
				<a class="bidPanel__btn bidPanel__btn--value" data-value="250">Capot</a>
			</div>

			<div class="bidPanel__colors">
				<a class="bidPanel__btn bidPanel__btn--color" data-color="1">
					<span class="card-color-icon card-color-icon--spade"/>
				</a>
				<a class="bidPanel__btn bidPanel__btn--color" data-color="2">
					<span class="card-color-icon card-color-icon--heart"/>
				</a>
				<a class="bidPanel__btn bidPanel__btn--color" data-color="4">
					<span class="card-color-icon card-color-icon--diamond"/>
				</a>
				<a class="bidPanel__btn bidPanel__btn--color" data-color="3">
					<span class="card-color-icon card-color-icon--club"/>
				</a>
				<a class="bidPanel__btn bidPanel__btn--color" data-color="5">
					<span class="card-color-icon card-color-icon--alltrump"/>
				</a>
				<a class="bidPanel__btn bidPanel__btn--color" data-color="6">
					<span class="card-color-icon card-color-icon--notrump"/>
				</a>
				<div class="bidPanel__spacer"></div>
				<a class="bidPanel__btn bidPanel__btn--coinche ">{COUNTER} !</a>
			</div>

		</div>

	</div>

</div>


<div class="myHand whiteblock">
	<div id="myHand" class="myHand__cards">
	</div>
	<div class="currentBidInfo">
		<div class="currentBidInfo__wrapper"></div>
	</div>
</div>

<script type="text/javascript">

	// Javascript HTML templates
	var jstpl_cardontable = '<div class="cardontable" id="cardontable_${player_id}" style="background-position:-${x}px -${y}px"></div>';
	var jstpl_currentbidinfo = '<div class="currentBidInfo__wrapper whiteblock">Enchère : <span class="currentBidInfo__bid">${bid}</span><span class="card-color-icon card-color-icon--size16 card-color-icon--${trumpColor}"></span> <span class="currentBidInfo__player">par <span>${bidPlayerDisplay}</span></span>  <div class="currentBidInfo__countered currentBidInfo__countered--${countered}">Coinché par ${counteringPlayerDisplay}</div> </div>';
	var jstpl_playerbid = '<div class="playerTables__bid-item"><span class="playerTables__bid__item-value">${bid}</span><span class="card-color-icon card-color-icon--size16 card-color-icon--${trumpColor}"></span></div>';
	var jstpl_playerpass = '<div class="playerTables__bid-item"><em>passe</em></div>';

</script>

{OVERALL_GAME_FOOTER}