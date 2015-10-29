<?php
/**
 * The TranslatableGoogleSitemaps module creates a Google Sitemap for
 * multilingual sites using The Translatable module, according to the Google
 * requirements here:
 *
 * https://support.google.com/webmasters/answer/2620865?hl=en&ref_topic=2370587
 *
 * Locales for alternatives can be added two ways: generic,
 * as in 'en' for all English speaking users, or specific, as
 * in 'en_uk'. Google seems to expect lowercase, although I'm not sure
 * that is actually required. You can define  certain locales as generic in your
 * _config/config.yml:
 *
 *  ---
 *  Name: translatablegooglesitemapconfig
 *  ---
 *  TranslatableGoogleSitemapController:
 *    GenericLocales:
 *      en_US: en
 *      nl_NL: nl
 *
 * @author Martine Bloem (Martimiz) <martimiz@gmail.com>
 * @version 1.0
 *
 * @package translatablegooglesitemaps
 */
class TranslatableGoogleSitemapController extends GoogleSitemapController {

	/**
	 * @var array
	 */
	private static $allowed_actions = array(
		'index',
		'sitemap'
	);

	/**
	 * We need to disable Translatable before retreiving the DataObjects or
	 * Pages for the sitemap, because otherwise only pages in the default
	 * language are found.
	 *
	 * Next we need to add the alternatives for each Translatable Object
	 * included in the sitemap: basically these are the Translations plus
	 * the current object itself
	 *
	 * @return Array
	 */
	public function sitemap() {
		Translatable::disable_locale_filter();
		$sitemap = parent::sitemap();
		Translatable::enable_locale_filter();

		$updatedItems = new ArrayList();

		foreach ($sitemap as $items) {
			foreach($items as $item) {
				if ($item->hasExtension('Translatable')) {
					$translations = $item->getTranslations();
					if ($translations) {
						$alternatives = new ArrayList();
						foreach($translations as $translation) {
							$translation->GoogleLocale = $this->getGoogleLocale($translation->Locale);
							$alternatives->push($translation);
						}
						$item->GoogleLocale = $this->getGoogleLocale($item->Locale);
						$alternatives->push($item);
						$item->Alternatives = $alternatives;
					}
					$updatedItems->push($item);
				}

				elseif ($item->hasExtension('TranslatableDataObject')) {
					$alternatives = new ArrayList();

					// get all allowed locales except default one
					$allowed_locales = Translatable::get_allowed_locales();
					$default_locale_key = array_search(Translatable::default_locale(), $allowed_locales);
					unset($allowed_locales[$default_locale_key]);

					foreach ($allowed_locales as $key => $locale) {
						Translatable::set_current_locale($locale);

						// get list of possible translated fields
						$localized_fields = TranslatableDataObject::get_localized_class_fields($item->ClassName);

						// check if at least one field is translated
						$isTranslated = false;
						foreach ($localized_fields as $key => $field) {
							$translated_field = $item->getLocalizedValue($field, true);
							if($translated_field->value) {
								$isTranslated = true;
							}
						}

						if($isTranslated) {
							$translation = new $item->ClassName();
							$translation->ID = $item->ID;
							$translation->locale_AbsoluteLink = $item->AbsoluteLink();
							$translation->GoogleLocale = $this->getGoogleLocale($locale);
							$alternatives->push($translation);
						}
					}
					Translatable::set_current_locale(Translatable::default_locale());

					if($isTranslated) {
						$item->GoogleLocale = $this->getGoogleLocale(Translatable::default_locale());
						$alternatives->push($item);
						$item->Alternatives = $alternatives;
					}

					$updatedItems->push($item);
				}
			}
		}
		// var_dump($updatedItems[0]->Alternatives);
		if ($updatedItems->exists()) {
			return array('Items' => $updatedItems);
		} else {
			return $sitemap;
		}
	}

	/**
	 *
	 * @param string $locale
	 * @return string
	 */
	public function getGoogleLocale($locale) {
		$genericLocales = Config::inst()->get('TranslatableGoogleSitemap', 'generic_locales');

		if (is_array($genericLocales) && array_key_exists($locale, $genericLocales)) {
			$locale = substr($locale, 0, 2);
		}
		return strtolower($locale);
	}

}

