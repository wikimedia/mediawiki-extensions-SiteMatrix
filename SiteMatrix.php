<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'SiteMatrix' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['SiteMatrix'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['SiteMatrixAliases'] = __DIR__ . '/SiteMatrix.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for SiteMatrix extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return true;
} else {
	die( 'This version of the SiteMatrix extension requires MediaWiki 1.25+' );
}
