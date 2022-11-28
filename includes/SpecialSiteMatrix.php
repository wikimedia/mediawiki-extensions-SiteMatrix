<?php

namespace MediaWiki\Extension\SiteMatrix;

use Html;
use LanguageCode;
use MediaWiki\Languages\LanguageNameUtils;
use SpecialPage;
use Xml;

class SpecialSiteMatrix extends SpecialPage {

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/**
	 * @param LanguageNameUtils $languageNameUtils
	 */
	public function __construct(
		LanguageNameUtils $languageNameUtils
	) {
		parent::__construct( 'SiteMatrix' );
		$this->languageNameUtils = $languageNameUtils;
	}

	public function execute( $par ) {
		$langNames = $this->languageNameUtils->getLanguageNames();

		$this->setHeaders();
		$this->outputHeader();

		$matrix = new SiteMatrix();

		$localLanguageNames = $this->languageNameUtils->getLanguageNames( $this->getLanguage()->getCode() );

		# Construct the HTML

		# Header row
		$s = Xml::openElement( 'table', [ 'class' => 'wikitable', 'id' => 'mw-sitematrix-table' ] ) .
			"<tr>" .
			Xml::element( 'th',
				[ 'rowspan' => 2 ],
				$this->msg( 'sitematrix-language' )->text() ) .
			Xml::element( 'th',
				[ 'colspan' => count( $matrix->getSites() ) ],
				$this->msg( 'sitematrix-project' )->text() ) .
			"</tr>
			<tr>";
		foreach ( $matrix->getNames() as $id => $name ) {
			$url = $matrix->getSiteUrl( $id );
			$s .= Xml::tags( 'th', null, "<a href=\"{$url}\">{$name}</a>" );
		}
		$s .= "</tr>\n";

		# Bulk of table
		foreach ( $matrix->getLangList() as $lang ) {
			if ( in_array( $lang, [ 'cz', 'dk', 'epo', 'jp', 'minnan', 'nan', 'nb', 'zh-cfr' ] ) ) {
				continue;
			}

			$s .= '<tr>';
			$attribs = [];
			if ( isset( $localLanguageNames[$lang] ) ) {
				$attribs['title'] = $localLanguageNames[$lang];
			}

			if ( isset( $langNames[$lang] ) ) {
				$langDisplay = Html::element( 'span',
					[ 'lang' => LanguageCode::bcp47( $lang ) ],
					$langNames[$lang] );
			} else {
				$langDisplay = '';
			}

			if ( isset( $localLanguageNames[$lang] ) &&
				strlen( $localLanguageNames[$lang] ) &&
				$langNames[$lang] !== $localLanguageNames[$lang]
			) {
				$langDisplay .= $this->msg( 'word-separator' )->escaped() .
					$this->msg( 'parentheses', $localLanguageNames[$lang] )->escaped();
			}
			$s .= Html::rawElement( 'td',
				[ 'id' => $lang ],
				Html::rawElement( 'strong', $attribs, $langDisplay )
			);

			foreach ( $matrix->getNames() as $site => $name ) {
				$url = $matrix->getUrl( $lang, $site );
				if ( $matrix->exist( $lang, $site ) ) {
					# Wiki exists
					$closed = $matrix->isClosed( $lang, $site );
					$s .= "<td>" . ( $closed ? "<del>" : '' ) .
						"<a href=\"{$url}\">{$lang}</a>" . ( $closed ? "</del>" : '' ) . '</td>';
				} else {
					# Non-existent wiki
					$s .= "<td><a href=\"{$url}\" class=\"new\">{$lang}</a></td>";
				}
			}
			$s .= "</tr>\n";
		}

		$language = $this->getLanguage();
		# Total
		$totalCount = 0;
		$s .= '<tr><th rowspan="2" id="total">' .
			$this->msg( 'sitematrix-sitetotal' )->escaped() . '</th>';
		foreach ( $matrix->getNames() as $site => $name ) {
			$url = $matrix->getSiteUrl( $site );
			$count = $matrix->getCountPerSite( $site );
			$totalCount += $count;
			$count = htmlspecialchars( $language->formatNum( $count ) );
			$s .= "<th><a href=\"{$url}\">{$count}</a></th>";
		}
		$s .= '</tr>';

		$s .= '<tr>';
		$noProjects = count( $matrix->getNames() );
		$totalCount = htmlspecialchars( $language->formatNum( $totalCount ) );
		$s .= "<th colspan=\"{$noProjects }\">{$totalCount}</th>";
		$s .= '</tr>';

		$s .= Xml::closeElement( 'table' ) . "\n";

		# Specials
		$s .= '<h2 id="mw-sitematrix-others">' . $this->msg( 'sitematrix-others' )->escaped() . '</h2>';

		$s .= Xml::openElement( 'table',
			[ 'class' => 'wikitable', 'id' => 'mw-sitematrix-other-table' ] ) .
			"<tr>" .
			Xml::element( 'th', null, $this->msg( 'sitematrix-other-projects' )->text() ) .
			Xml::element( 'th', null, $this->msg( 'sitematrix-other-projects-language' )->text() ) .
			"</tr>";

		foreach ( $matrix->getSpecials() as $special ) {
			list( $lang, $site ) = $special;

			// sanity check
			if ( !$lang && !$site ) {
				continue;
			}

			$langhost = str_replace( '_', '-', $lang );
			$url = $matrix->getUrl( $lang, $site );

			# Handle options
			$flags = [];
			if ( $matrix->isPrivate( $lang . $site ) ) {
				$flags[] = $this->msg( 'sitematrix-private' )->escaped();
			}
			if ( $matrix->isFishbowl( $lang . $site ) ) {
				$flags[] = $this->msg( 'sitematrix-fishbowl' )->escaped();
			}
			if ( $matrix->isNonGlobal( $lang . $site ) ) {
				$flags[] = $this->msg( 'sitematrix-nonglobal' )->escaped();
			}
			$flagsStr = implode( ', ', $flags );
			if ( $site != 'wiki' ) {
				$langhost .= $site;
			}
			$closed = $matrix->isClosed( $lang, $site );
			$s .= '<tr><td>' . ( $closed ? '<del>' : '' ) .
				$language->specialList( '<a href="' . $url . '/">' . $langhost . "</a>", $flagsStr ) .
				( $closed ? '</del>' : '' ) . '</td>';
			$s .= '<td>' . $matrix->getLanguageCode( $lang, $site ) . '</td>';
			$s .= "</tr>\n";
		}

		$s .= Xml::closeElement( 'table' ) . "\n";

		$this->getOutput()->addHTML( $s );
		$this->getOutput()->addWikiMsg( 'sitematrix-total', $language->formatNum( $matrix->getCount() ) );
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
