<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    echo "SiteMatrix extension\n";
    exit( 1 );
}

global $wgMessageCache, $IP;
$wgMessageCache->addMessage( 'sitematrix', 'List of Wikimedia wikis' );

require_once( $IP.'/languages/Names.php' );

class SiteMatrixPage extends SpecialPage {

	function SiteMatrixPage() {
		SpecialPage::SpecialPage('SiteMatrix');
	}

	function execute( $par ) {
		global $wgOut, $wgLocalDatabases;
		$this->setHeaders();

		$langlist = array_map( 'trim', file( '/home/wikipedia/common/langlist' ) );
		sort( $langlist );
		$xLanglist = array_flip( $langlist );

		$sites = array( 'wiki', 'wiktionary', 'wikibooks', 'wikinews', 'wikisource', 'wikiquote' );
		$names = array( 
			'wiki' => 'Wikipedia<br />w',
			'wiktionary' => 'Wiktionary<br />wikt',
			'wikibooks' => 'Wikibooks<br />b',
			'wikinews' => 'Wikinews<br />n',
			'wikiquote' => 'Wikiquote<br />q',
			'wikisource' => 'Wikisource<br />s',
		);
		$hosts = array(
			'wiki' => 'wikipedia.org',
			'wiktionary' => 'wiktionary.org',
			'wikibooks' => 'wikibooks.org',
			'wikinews' => 'wikinews.org',
			'wikisource' => 'wikisource.org',
			'wikiquote' => 'wikiquote.org',
		);

		# Special wikis that should point to wikiPedia, not wikiMedia
		$wikipediaSpecial = array(
			'aa', 'bat_smg', 'closed_zh_tw', 'dk', 'fiu_vro', 'map_bms', 'nds_nl',
			'roa_rup', 'sep11', 'sources', 'species', 'test', 'zh_min_nan', 'zh_yue',
		);

		# Some internal databases for other domains.
		$hidden = array(
			'foundation', 'mediawiki',
		);
		
		# Tabulate the matrix
		$specials = array();
		$matrix = array();
		foreach( $wgLocalDatabases as $db ) {
			# Find suffix
			foreach ( $sites as $site ) {
				$m = array();
				if ( preg_match( "/(.*)$site\$/", $db, $m ) ) {
					$lang =  $m[1];
					if ( empty( $xLanglist[$lang] ) && $site == 'wiki' ) {
						$specials[] = $lang;
					} else {
						$matrix[$site][$lang] = 1;
					}
					break;
				}
			}
		}

		# Construct the HTML

		# Header row
		$s = '<table><tr>';
		$s .= '<th>Language</th>';
		foreach ( $names as $name ) {
			$s .= '<th>' . $name . '</th>';
		}
		$s .= "</tr>\n";

		global $wgLanguageNames;
		# Bulk of table
		foreach ( $langlist as $lang ) {
			$anchor = strtolower( '<a id="' . htmlspecialchars( $lang ) . '" name="' . htmlspecialchars( $lang ) . '"></a>' );
			$s .= '<tr>';
			$s .= '<td>' . $anchor . '<strong>' . $wgLanguageNames[$lang] . '</strong></td>';
			$langhost = str_replace( '_', '-', $lang );
			foreach ( $names as $site => $name ) {
				$url = "http://$langhost." . $hosts[$site] . '/';
				if ( empty( $matrix[$site][$lang] ) ) {
					# Non-existent wiki
					$s .= '<td><a href="' . $url . '" class="new">' . $lang . '</a></td>';
				} else {
					# Wiki exists
					$s .= '<td><a href="' . $url . '">' . $lang . '</a></td>';
				}
			}
			$s .= "</tr>\n";
		}
		$s .= "</table>\n";

		# Specials
		$s .= '<ul>';
		foreach ( $specials as $lang ) {

			# Skip "hidden" databases:
			if( in_array($lang, $hidden) ) {
				continue;
			}

			$langhost = str_replace( '_', '-', $lang );

			# Handle special wikipedia projects:
			if( in_array($lang, $wikipediaSpecial) ) {
				$domain = '.wikipedia.org';
			} else{
				$domain = '.wikimedia.org';
			}
			$s .= '<li><a href="http://' . $langhost . $domain . '/">' . $lang . "</a></li>\n";
		}
		$s .= '</ul>';
		$wgOut->addHTML( $s );
	}
}

?>
