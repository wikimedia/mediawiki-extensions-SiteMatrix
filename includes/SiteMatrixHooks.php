<?php

/**
 * Hook handlers
 */
class SiteMatrixHooks {
	/**
	 * Handler method for the APISiteInfoGeneralInfo hook
	 *
	 * @param ApiQuerySiteinfo $module
	 * @param array &$results
	 * @return bool
	 */
	public static function APIQuerySiteInfoGeneralInfo( $module, &$results ) {
		global $wgDBname, $wgConf;

		$matrix = new SiteMatrix();

		list( $site, $lang ) = $wgConf->siteFromDB( $wgDBname );

		if ( $matrix->isClosed( $lang, $site ) ) {
			$results['closed'] = '';
		}

		if ( $matrix->isSpecial( $wgDBname ) ) {
			$results['special'] = '';
		}

		if ( $matrix->isPrivate( $wgDBname ) ) {
			$results['private'] = '';
		}

		if ( $matrix->isFishbowl( $wgDBname ) ) {
			$results['fishbowl'] = '';
		}

		if ( $matrix->isNonGlobal( $wgDBname ) ) {
			$results['nonglobal'] = '';
		}

		return true;
	}

	/**
	 * @param Parser &$parser
	 * @param array &$cache
	 * @param string &$magicWordId
	 * @param string &$ret
	 * @param PPFrame $frame
	 * @return bool true
	 */
	public static function onParserGetVariableValueSwitch(
		Parser &$parser,
		&$cache,
		&$magicWordId,
		&$ret,
		$frame = null ) {
		if ( $magicWordId == 'numberofwikis' ) {
			global $wgLocalDatabases;
			$ret = count( $wgLocalDatabases );
		}
		return true;
	}

	/**
	 * @param array &$customVariableIds
	 * @return bool true
	 */
	public static function onMagicWordwgVariableIDs( &$customVariableIds ) {
		$customVariableIds[] = 'numberofwikis';
		return true;
	}
}
