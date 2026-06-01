<?php
/**
 * Name Translator
 *
 * Converts Japanese field names to alphanumeric keys. Used by migration tools such as MW WP Form.
 *
 * @package Form_Plant
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FPLANT_Name_Translator
 *
 * Intended to be instantiated per form and reused to guarantee consistent
 * translations within a single form. Call reset() when reusing the instance.
 *
 * @since 1.2.0
 */
class FPLANT_Name_Translator {

	/**
	 * Dictionary mapping known Japanese field names to alphanumeric keys.
	 *
	 * @var array<string, string>
	 */
	private static $dictionary = array(
		'お名前'           => 'your_name',
		'名前'             => 'name',
		'お名前（漢字）'   => 'name',
		'お名前(漢字)'     => 'name',
		'お名前（カナ）'   => 'name_kana',
		'お名前(カナ)'     => 'name_kana',
		'フリガナ'         => 'name_kana',
		'ふりがな'         => 'name_kana',
		'姓'               => 'last_name',
		'名'               => 'first_name',
		'セイ'             => 'last_name_kana',
		'メイ'             => 'first_name_kana',
		'メールアドレス'   => 'email',
		'メール'           => 'email',
		'Eメール'          => 'email',
		'電話番号'         => 'tel',
		'電話'             => 'tel',
		'TEL'              => 'tel',
		'FAX'              => 'fax',
		'郵便番号'         => 'zip',
		'〒'               => 'zip',
		'都道府県'         => 'prefecture',
		'住所'             => 'address',
		'ご住所'           => 'address',
		'会社名'           => 'company',
		'会社'             => 'company',
		'企業名'           => 'company',
		'部署'             => 'department',
		'役職'             => 'position',
		'お問い合わせ'     => 'inquiry',
		'お問合せ'         => 'inquiry',
		'問い合わせ内容'   => 'inquiry',
		'お問い合わせ内容' => 'inquiry',
		'ご質問'           => 'inquiry',
		'メッセージ'       => 'message',
		'本文'             => 'message',
		'内容'             => 'content',
		'備考'             => 'note',
		'生年月日'         => 'birth_date',
		'年齢'             => 'age',
		'性別'             => 'gender',
		'ご希望日'         => 'desired_date',
		'URL'              => 'url',
		'ホームページ'     => 'url',
		'件名'             => 'subject',
		'タイトル'         => 'title',
		'カテゴリ'         => 'category',
		'カテゴリー'       => 'category',
		'パスワード'       => 'password',
		'ファイル'         => 'file',
		'画像'             => 'image',
		'添付ファイル'     => 'attachment',
	);

	/**
	 * Map of original names to translated names, kept consistent within a single form.
	 *
	 * @var array<string, string>
	 */
	private $name_map = array();

	/**
	 * List of already-used translated names (for collision detection).
	 *
	 * @var array<int, string>
	 */
	private $used_names = array();

	/**
	 * Translate a Japanese name to an alphanumeric key.
	 *
	 * @param string $original_name The original field name.
	 * @param string $type_hint     Field type hint used for fallback name generation
	 *                              (e.g. 'text', 'email', 'textarea').
	 *                              Ignored when a dictionary match or ASCII pass-through occurs.
	 * @return string The translated name (always matches `/^[a-zA-Z0-9_]+$/`).
	 */
	public function translate( $original_name, $type_hint = '' ) {
		$original_name = (string) $original_name;

		if ( isset( $this->name_map[ $original_name ] ) ) {
			return $this->name_map[ $original_name ];
		}

		$candidate = $this->derive_candidate( $original_name, (string) $type_hint );

		$final  = $candidate;
		$suffix = 2;
		while ( in_array( $final, $this->used_names, true ) ) {
			$final = $candidate . '_' . $suffix;
			++$suffix;
		}

		$this->name_map[ $original_name ] = $final;
		$this->used_names[]               = $final;

		return $final;
	}

	/**
	 * Return the map of names translated so far. Used for replacing mail-body merge tags, etc.
	 *
	 * @return array<string, string>
	 */
	public function get_map() {
		return $this->name_map;
	}

	/**
	 * Reset the internal state.
	 */
	public function reset() {
		$this->name_map   = array();
		$this->used_names = array();
	}

	/**
	 * Derive a candidate alphanumeric key before collision resolution.
	 *
	 * @param string $original_name The original field name.
	 * @param string $type_hint     Field type hint used for fallback generation.
	 * @return string The candidate key.
	 */
	private function derive_candidate( $original_name, $type_hint = '' ) {
		$trimmed = trim( $original_name );

		if ( '' !== $trimmed && preg_match( '/^[a-zA-Z0-9_]+$/', $trimmed ) ) {
			return $trimmed;
		}

		if ( isset( self::$dictionary[ $trimmed ] ) ) {
			return self::$dictionary[ $trimmed ];
		}

		$prefix = $this->normalize_type_hint( $type_hint );
		return $prefix . '_' . ( $this->count_fallback_for_prefix( $prefix ) + 1 );
	}

	/**
	 * Normalise a field type hint to a safe alphanumeric prefix.
	 *
	 * @param string $type_hint The type hint.
	 * @return string 'field' (default) or the type name itself.
	 */
	private function normalize_type_hint( $type_hint ) {
		$type_hint = strtolower( trim( $type_hint ) );
		if ( '' === $type_hint || ! preg_match( '/^[a-z0-9_]+$/', $type_hint ) ) {
			return 'field';
		}
		return $type_hint;
	}

	/**
	 * Count how many fallback names have been generated for the given prefix.
	 *
	 * Sequential numbering is restricted to fallback names only. Including
	 * dictionary hits or ASCII pass-throughs (e.g. a user-supplied `text_1`)
	 * in the count would cause gaps in the sequence. Only names that strictly
	 * match `/^{prefix}_\d+$/` are counted.
	 *
	 * @param string $prefix The type prefix.
	 * @return int The number of matching entries.
	 */
	private function count_fallback_for_prefix( $prefix ) {
		$pattern = '/^' . preg_quote( $prefix, '/' ) . '_\d+$/';
		$count   = 0;
		foreach ( $this->used_names as $used ) {
			if ( preg_match( $pattern, $used ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Return the dictionary (for testing).
	 *
	 * @return array<string, string>
	 */
	public static function get_dictionary() {
		return self::$dictionary;
	}
}
