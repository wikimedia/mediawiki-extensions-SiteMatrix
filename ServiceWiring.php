<?php

declare( strict_types=1 );

use MediaWiki\Extension\SiteMatrix\SiteMatrix;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'SiteMatrix' => static function ( MediaWikiServices $services ): SiteMatrix {
		return new SiteMatrix();
	},
];
