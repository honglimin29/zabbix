<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations media types.
 */
class CMediatype extends CApiService {

	protected $tableName = 'media_type';
	protected $tableAlias = 'mt';
	protected $sortColumns = ['mediatypeid'];

	/**
	 * Get Media types data
	 *
	 * @param array $options
	 * @param array $options['mediatypeids'] filter by Mediatype IDs
	 * @param boolean $options['type'] filter by Mediatype type [ USER_TYPE_ZABBIX_USER: 1, USER_TYPE_ZABBIX_ADMIN: 2, USER_TYPE_SUPER_ADMIN: 3 ]
	 * @param boolean $options['output'] output only Mediatype IDs if not set.
	 * @param boolean $options['count'] output only count of objects in result. ( result returned in property 'rowscount' )
	 * @param string $options['pattern'] filter by Host name containing only give pattern
	 * @param int $options['limit'] output will be limited to given number
	 * @param string $options['sortfield'] output will be sorted by given property [ 'mediatypeid', 'alias' ]
	 * @param string $options['sortorder'] output will be sorted in given order [ 'ASC', 'DESC' ]
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['media_type' => 'mt.mediatypeid'],
			'from'		=> ['media_type' => 'media_type mt'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'mediatypeids'				=> null,
			'mediaids'					=> null,
			'userids'					=> null,
			'editable'					=> false,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectUsers'				=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// permission check
		if (self::$userData['type'] == USER_TYPE_SUPER_ADMIN) {
		}
		elseif (!$options['editable'] && self::$userData['type'] == USER_TYPE_ZABBIX_ADMIN) {
		}
		elseif ($options['editable'] || self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			return [];
		}

		// mediatypeids
		if (!is_null($options['mediatypeids'])) {
			zbx_value2array($options['mediatypeids']);
			$sqlParts['where'][] = dbConditionInt('mt.mediatypeid', $options['mediatypeids']);
		}

		// mediaids
		if (!is_null($options['mediaids'])) {
			zbx_value2array($options['mediaids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.mediaid', $options['mediaids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		// userids
		if (!is_null($options['userids'])) {
			zbx_value2array($options['userids']);

			$sqlParts['from']['media'] = 'media m';
			$sqlParts['where'][] = dbConditionInt('m.userid', $options['userids']);
			$sqlParts['where']['mmt'] = 'm.mediatypeid=mt.mediatypeid';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('media_type mt', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('media_type mt', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($mediatype = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $mediatype;
				}
				else {
					$result = $mediatype['rowscount'];
				}
			}
			else {
				$result[$mediatype['mediatypeid']] = $mediatype;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	/**
	 * Validates the input parameters for the create() method.
	 *
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateCreate(array $mediatypes) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create media types.'));
		}

		if (!$mediatypes) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$required_fields = ['type', 'name'];

		foreach ($mediatypes as $mediatype) {
			if (!is_array($mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			// Check required parameters.
			$missing_keys = array_diff($required_fields, array_keys($mediatype));

			if ($missing_keys) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Media type is missing parameters: %1$s', implode(', ', $missing_keys))
				);
			}
			else {
				foreach ($required_fields as $field) {
					if ($mediatype[$field] === '' || $mediatype[$field] === null) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Field "%1$s" is missing a value for media type "%2$s".', $field, $mediatype['name'])
						);
					}
				}
			}
		}

		// Check for duplicate names.
		$duplicate_name = CArrayHelper::findDuplicate($mediatypes, 'name');
		if ($duplicate_name) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%1$s" already exists.', $duplicate_name['name']));
		}

		$simple_interval_parser = new CSimpleIntervalParser();

		$mediatype_rules = $this->getValidationRules();

		foreach ($mediatypes as $mediatype) {
			// Check if media type already exists.
			$db_mediatype = API::getApiService()->select('media_type', [
				'output' => ['name'],
				'filter' => ['name' => $mediatype['name']],
				'limit' => 1
			]);

			if ($db_mediatype) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media type "%1$s" already exists.', $mediatype['name']));
			}

			// Check additional fields and values depeding on type.
			$this->checkRequiredFieldsByType($mediatype);

			$validation_rules = $mediatype_rules['fields'];
			$validation_rules += array_key_exists($mediatype['type'], $mediatype_rules)
				? $mediatype_rules[$mediatype['type']]
				: [];
			$validated_data = array_intersect_key($mediatype, $validation_rules);

			switch ($mediatype['type']) {
				case MEDIA_TYPE_EMAIL:
					if (array_key_exists('smtp_authentication', $mediatype)) {
						$smtp_authentication_validator = new CLimitedSetValidator([
							'values' => [SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_NORMAL]
						]);

						if (!$smtp_authentication_validator->validate($mediatype['smtp_authentication'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_authentication'],
								'smtp_authentication',
								$mediatype['name']
							));
						}
					}

					// Validate optional 'smtp_port' field.
					if (array_key_exists('smtp_port', $mediatype) && !validatePortNumber($mediatype['smtp_port'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
							$mediatype['smtp_port'],
							'smtp_port',
							$mediatype['name']
						));
					}

					// Validate optional field 'smtp_security'.
					if (array_key_exists('smtp_security', $mediatype)) {
						$smtp_security_validator = new CLimitedSetValidator([
							'values' => [
								SMTP_CONNECTION_SECURITY_NONE,
								SMTP_CONNECTION_SECURITY_STARTTLS,
								SMTP_CONNECTION_SECURITY_SSL_TLS
							]
						]);

						if (!$smtp_security_validator->validate($mediatype['smtp_security'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_security'],
								'smtp_security',
								$mediatype['name']
							));
						}
					}

					// Validate optional field 'smtp_verify_peer'.
					if (array_key_exists('smtp_verify_peer', $mediatype)) {
						$smtp_verify_peer_validator = new CLimitedSetValidator([
							'values' => [0, 1]
						]);

						if (!$smtp_verify_peer_validator->validate($mediatype['smtp_verify_peer'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_verify_peer'],
								'smtp_verify_peer',
								$mediatype['name']
							));
						}
					}

					// Validate optional field 'smtp_verify_host'.
					if (array_key_exists('smtp_verify_host', $mediatype)) {
						$smtp_verify_host_validator = new CLimitedSetValidator([
							'values' => [0, 1]
						]);

						if (!$smtp_verify_host_validator->validate($mediatype['smtp_verify_host'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_verify_host'],
								'smtp_verify_host',
								$mediatype['name']
							));
						}
					}

					// Validate optional field 'content_type'.
					if (array_key_exists('content_type', $mediatype)) {
						$content_type_validator = new CLimitedSetValidator([
							'values' => [
								SMTP_MESSAGE_FORMAT_PLAIN_TEXT,
								SMTP_MESSAGE_FORMAT_HTML
							]
						]);

						if (!$content_type_validator->validate($mediatype['content_type'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['content_type'],
								'content_type',
								$mediatype['name']
							));
						}
					}
					break;

				case MEDIA_TYPE_EXEC:
					if (array_key_exists('exec_params', $mediatype) && $mediatype['exec_params'] !== '') {
						$pos = strrpos($mediatype['exec_params'], "\n");

						if ($pos === false || strlen($mediatype['exec_params']) != $pos + 1) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Script parameters "%1$s" are missing the last new line feed for media type "%2$s".',
								$mediatype['exec_params'],
								$mediatype['name']
							));
						}
					}
					break;

				case MEDIA_TYPE_WEBHOOK:
					if (array_key_exists('receive_tags', $validated_data)
							&& $validated_data['receive_tags'] == MEDIA_TYPE_TAGS_DISABLED) {
						unset($validation_rules['url'], $validation_rules['url_name']);
					}

					$params = [];

					foreach ($validated_data['params'] as $index => $param) {
						if (array_key_exists($param['name'], $params)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Invalid parameter "%1$s": %2$s.', '/params/'.$index.'/name',
									_s('value "%1$s" already exists', $param['name'])
							));
						}

						$params[$param['name']] = true;
					}
					break;
			}

			if ($validation_rules && !CApiInputValidator::validate([
					'type' => API_OBJECT,
					'fields' => $validation_rules], $validated_data, '', $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			// Validate optional 'status' field.
			if (array_key_exists('status', $mediatype)) {
				$status_validator = new CLimitedSetValidator([
					'values' => [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED]
				]);

				if (!$status_validator->validate($mediatype['status'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
						$mediatype['status'],
						'status',
						$mediatype['name']
					));
				}
			}

			// Validate optional 'maxsessions' field.
			if (array_key_exists('maxsessions', $mediatype)) {
				if ($mediatype['maxsessions'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxsessions', _('cannot be empty')
					));
				}

				$min = ($mediatype['type'] == MEDIA_TYPE_SMS) ? 1 : 0;
				$max = ($mediatype['type'] == MEDIA_TYPE_SMS) ? 1 : 100;

				if (!ctype_digit((string) $mediatype['maxsessions']) || $mediatype['maxsessions'] > $max
						|| $mediatype['maxsessions'] < $min) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxsessions', _s('must be between "%1$s" and "%2$s"', $min, $max)
					));
				}
			}

			// Validate optional 'maxattempts' field.
			if (array_key_exists('maxattempts', $mediatype)) {
				if ($mediatype['maxattempts'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxattempts', _('cannot be empty')
					));
				}

				if (!ctype_digit((string) $mediatype['maxattempts']) || $mediatype['maxattempts'] > 10
						|| $mediatype['maxattempts'] < 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxattempts', _s('must be between "%1$s" and "%2$s"', 1, 10)
					));
				}
			}

			// Validate optional 'attempt_interval' field.
			if (array_key_exists('attempt_interval', $mediatype)) {
				if ($mediatype['attempt_interval'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'attempt_interval', _('cannot be empty')
					));
				}

				if ($simple_interval_parser->parse($mediatype['attempt_interval']) == CParser::PARSE_SUCCESS) {
					$attempt_interval = timeUnitToSeconds($mediatype['attempt_interval']);

					if ($attempt_interval < 0 || $attempt_interval > 60) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'attempt_interval', _s('must be between "%1$s" and "%2$s"', 0, 60)
						));
					}
				}
				else {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'attempt_interval', _s('must be between "%1$s" and "%2$s"', 0, 60)
					));
				}
			}
		}
	}

	/**
	 * Validates the input parameters for the update() method.
	 *
	 * @param array $mediatypes
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateUpdate(array $mediatypes) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can edit media types.'));
		}

		if (!$mediatypes) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		// Validate given IDs.
		$this->checkObjectIds($mediatypes, 'mediatypeid',
			_('No "%1$s" given for media type.'),
			_('Empty media type ID.'),
			_('Incorrect media type ID.')
		);

		$mediatypeids = zbx_objectValues($mediatypes, 'mediatypeid');

		// Check value map names.
		$db_mediatypes = API::getApiService()->select('media_type', [
			'output' => ['mediatypeid', 'type', 'name', 'exec_path', 'status', 'smtp_port', 'smtp_verify_peer',
				'smtp_verify_host', 'smtp_authentication', 'maxsessions', 'maxattempts', 'attempt_interval',
				'content_type', 'webhook', 'timeout', 'receive_tags', 'url', 'url_name'
			],
			'mediatypeids' => $mediatypeids,
			'preservekeys' => true
		]);

		$check_names = [];
		$simple_interval_parser = new CSimpleIntervalParser();

		foreach ($mediatypes as $mediatype) {
			// Check if this media type exists.
			if (!array_key_exists($mediatype['mediatypeid'], $db_mediatypes)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			// Validate "name" field.
			if (array_key_exists('name', $mediatype)) {
				if (is_array($mediatype['name'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}
				elseif ($mediatype['name'] === '' || $mediatype['name'] === null || $mediatype['name'] === false) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'name', _('cannot be empty'))
					);
				}

				$check_names[$mediatype['name']] = true;
			}
		}

		if ($check_names) {
			$db_mediatype_names = API::getApiService()->select('media_type', [
				'output' => ['mediatypeid', 'name'],
				'filter' => ['name' => array_keys($check_names)]
			]);
			$db_mediatype_names = zbx_toHash($db_mediatype_names, 'name');

			foreach ($mediatypes as $mediatype) {
				if (array_key_exists('name', $mediatype)
						&& array_key_exists($mediatype['name'], $db_mediatype_names)
						&& !idcmp($db_mediatype_names[$mediatype['name']]['mediatypeid'], $mediatype['mediatypeid'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Media type "%1$s" already exists.', $mediatype['name'])
					);
				}
			}
		}

		// Populate "name" field, if not set. Type field should not be populated at this point.
		$mediatypes = $this->extendFromObjects(zbx_toHash($mediatypes, 'mediatypeid'), $db_mediatypes, ['name']);

		$duplicate_name = CArrayHelper::findDuplicate($mediatypes, 'name');
		if ($duplicate_name) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Media type "%1$s" already exists.', $duplicate_name['name'])
			);
		}

		$mediatype_rules = $this->getValidationRules();

		foreach ($mediatypes as $mediatype) {
			$db_mediatype = $db_mediatypes[$mediatype['mediatypeid']];

			// Recheck mandatory fields if type changed.
			if (array_key_exists('type', $mediatype) && $db_mediatype['type'] != $mediatype['type']) {
				$this->checkRequiredFieldsByType($mediatype);
			}
			else {
				$optional_fields_by_type = [
					MEDIA_TYPE_EMAIL => ['smtp_server', 'smtp_helo', 'smtp_email'],
					MEDIA_TYPE_EXEC => ['exec_path'],
					MEDIA_TYPE_WEBHOOK => [],
					MEDIA_TYPE_SMS => ['gsm_modem']
				];

				foreach ($optional_fields_by_type[$db_mediatype['type']] as $field) {
					if (array_key_exists($field, $mediatype)
							&& ($mediatype[$field] === '' || $mediatype[$field] === null)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Field "%1$s" is missing a value for media type "%2$s".',
							$field,
							$mediatype['name']
						));
					}
				}

				// Populate "type" field from DB, since it is not set and is required for further validation.
				$mediatype['type'] = $db_mediatype['type'];
			}

			$validation_rules = $mediatype_rules['fields'];
			$validated_data = [];

			if (array_key_exists($mediatype['type'], $mediatype_rules)) {
				$validation_rules += $mediatype_rules[$mediatype['type']];
				$validated_data = array_intersect_key($mediatype, $validation_rules);
			}

			switch ($mediatype['type']) {
				case MEDIA_TYPE_EMAIL:
					if (array_key_exists('smtp_authentication', $mediatype)) {
						$smtp_authentication_validator = new CLimitedSetValidator([
							'values' => [SMTP_AUTHENTICATION_NONE, SMTP_AUTHENTICATION_NORMAL]
						]);

						if (!$smtp_authentication_validator->validate($mediatype['smtp_authentication'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_authentication'],
								'smtp_authentication',
								$mediatype['name']
							));
						}
					}

					// Validate optional 'smtp_port' field.
					if (array_key_exists('smtp_port', $mediatype)
							&& $db_mediatype['smtp_port'] != $mediatype['smtp_port']
							&& !validatePortNumber($mediatype['smtp_port'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s(
							'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
							$mediatype['smtp_port'],
							'smtp_port',
							$mediatype['name']
						));
					}

					// Validate optional field 'smtp_security'.
					if (array_key_exists('smtp_security', $mediatype)) {
						$smtp_security_validator = new CLimitedSetValidator([
							'values' => [
								SMTP_CONNECTION_SECURITY_NONE,
								SMTP_CONNECTION_SECURITY_STARTTLS,
								SMTP_CONNECTION_SECURITY_SSL_TLS
							]
						]);

						if (!$smtp_security_validator->validate($mediatype['smtp_security'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_security'],
								'smtp_security',
								$mediatype['name']
							));
						}
					}

					// Validate optional field 'smtp_verify_peer'.
					if (array_key_exists('smtp_verify_peer', $mediatype)
							&& $db_mediatype['smtp_verify_peer'] != $mediatype['smtp_verify_peer']) {
						$smtp_verify_peer_validator = new CLimitedSetValidator([
							'values' => [0, 1]
						]);

						if (!$smtp_verify_peer_validator->validate($mediatype['smtp_verify_peer'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_verify_peer'],
								'smtp_verify_peer',
								$mediatype['name']
							));
						}
					}

					// Validate optional field 'smtp_verify_host'.
					if (array_key_exists('smtp_verify_host', $mediatype)
							&& $db_mediatype['smtp_verify_host'] != $mediatype['smtp_verify_host']) {
						$smtp_verify_host_validator = new CLimitedSetValidator([
							'values' => [0, 1]
						]);

						if (!$smtp_verify_host_validator->validate($mediatype['smtp_verify_host'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['smtp_verify_host'],
								'smtp_verify_host',
								$mediatype['name']
							));
						}
					}

					// Validate optional field 'content_type'.
					if (array_key_exists('content_type', $mediatype)
							&& $db_mediatype['content_type'] != $mediatype['content_type']) {
						$content_type_validator = new CLimitedSetValidator([
							'values' => [
								SMTP_MESSAGE_FORMAT_PLAIN_TEXT,
								SMTP_MESSAGE_FORMAT_HTML
							]
						]);

						if (!$content_type_validator->validate($mediatype['content_type'])) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
								$mediatype['content_type'],
								'content_type',
								$mediatype['name']
							));
						}
					}
					break;

				case MEDIA_TYPE_EXEC:
					if (array_key_exists('exec_params', $mediatype) && $mediatype['exec_params'] !== '') {
						$pos = strrpos($mediatype['exec_params'], "\n");

						if ($pos === false || strlen($mediatype['exec_params']) != $pos + 1) {
							self::exception(ZBX_API_ERROR_PARAMETERS, _s(
								'Script parameters "%1$s" are missing the last new line feed for media type "%2$s".',
								$mediatype['exec_params'],
								$mediatype['name']
							));
						}
					}
					break;

				case MEDIA_TYPE_WEBHOOK:
					$validated_data += [
						'webhook' => $db_mediatype['webhook'],
						'receive_tags' => $db_mediatype['receive_tags'],
						'params' => []
					];

					if ($validated_data['receive_tags'] == MEDIA_TYPE_TAGS_DISABLED) {
						unset($validation_rules['url'], $validation_rules['url_name']);
					}

					$params = [];

					foreach ($validated_data['params'] as $index => $param) {
						if (array_key_exists($param['name'], $params)) {
							self::exception(ZBX_API_ERROR_PARAMETERS,
								_s('Invalid parameter "%1$s": %2$s.', '/params/'.$index.'/name',
									_s('value "%1$s" already exists', $param['name'])
							));
						}

						$params[$param['name']] = true;
					}
					break;
			}

			if ($validation_rules && !CApiInputValidator::validate([
					'type' => API_OBJECT,
					'fields' => $validation_rules], $validated_data, '', $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			// Validate optional 'status' field and only when status is changed.
			if (array_key_exists('status', $mediatype) && $db_mediatype['status'] != $mediatype['status']) {
				$status_validator = new CLimitedSetValidator([
					'values' => [MEDIA_TYPE_STATUS_ACTIVE, MEDIA_TYPE_STATUS_DISABLED]
				]);

				if (!$status_validator->validate($mediatype['status'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s(
						'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
						$mediatype['status'],
						'status',
						$mediatype['name']
					));
				}
			}

			// Validate optional 'maxsessions' field.
			if (array_key_exists('maxsessions', $mediatype)
					&& $db_mediatype['maxsessions'] != $mediatype['maxsessions']) {
				if ($mediatype['maxsessions'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxsessions', _('cannot be empty')
					));
				}

				$min = ($mediatype['type'] == MEDIA_TYPE_SMS) ? 1 : 0;
				$max = ($mediatype['type'] == MEDIA_TYPE_SMS) ? 1 : 100;

				if (!ctype_digit((string) $mediatype['maxsessions']) || $mediatype['maxsessions'] > $max
						|| $mediatype['maxsessions'] < $min) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxsessions', _s('must be between "%1$s" and "%2$s"', $min, $max)
					));
				}
			}
			elseif ($mediatype['type'] == MEDIA_TYPE_SMS && $mediatype['type'] != $db_mediatype['type']
						&& $db_mediatype['maxsessions'] != 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'maxsessions', _s('must be between "%1$s" and "%2$s"', 1, 1)
				));
			}

			// Validate optional 'maxattempts' field.
			if (array_key_exists('maxattempts', $mediatype)
					&& $db_mediatype['maxattempts'] != $mediatype['maxattempts']) {
				if ($mediatype['maxattempts'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxattempts', _('cannot be empty')
					));
				}

				if (!ctype_digit((string) $mediatype['maxattempts']) || $mediatype['maxattempts'] > 10
						|| $mediatype['maxattempts'] < 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'maxattempts', _s('must be between "%1$s" and "%2$s"', 1, 10)
					));
				}
			}

			// Validate optional 'attempt_interval' field.
			if (array_key_exists('attempt_interval', $mediatype)
					&& $db_mediatype['attempt_interval'] != $mediatype['attempt_interval']) {
				if ($mediatype['attempt_interval'] === '') {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'attempt_interval', _('cannot be empty')
					));
				}

				if ($simple_interval_parser->parse($mediatype['attempt_interval']) == CParser::PARSE_SUCCESS) {
					$attempt_interval = timeUnitToSeconds($mediatype['attempt_interval']);

					if ($attempt_interval < 0 || $attempt_interval > 60) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
							'attempt_interval', _s('must be between "%1$s" and "%2$s"', 0, 60)
						));
					}
				}
				else {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
						'attempt_interval', _s('must be between "%1$s" and "%2$s"', 0, 60)
					));
				}
			}
		}
	}

	/**
	 * Add Media types.
	 *
	 * @param array		$mediatypes							multidimensional array with media types data
	 * @param int		$mediatypes['type']					type
	 * @param string	$mediatypes['name']
	 * @param string	$mediatypes['smtp_server']			SMTP server
	 * @param int		$mediatypes['smtp_port']			SMTP port
	 * @param string	$mediatypes['smtp_helo']			SMTP hello
	 * @param string	$mediatypes['smtp_email']			SMTP email
	 * @param int		$mediatypes['smtp_security']		SMTP connection security
	 * @param int		$mediatypes['smtp_verify_peer']		SMTP verify peer
	 * @param int		$mediatypes['smtp_verify_host']		SMTP verify host
	 * @param int		$mediatypes['smtp_authentication']	SMTP authentication
	 * @param int		$mediatypes['content_type']			Message format
	 * @param string	$mediatypes['exec_path']			script name/message text limit
	 * @param string	$mediatypes['exec_params']			script parameters
	 * @param string	$mediatypes['gsm_modem']			GSM modem
	 * @param string	$mediatypes['username']				username
	 * @param string	$mediatypes['passwd']				password
	 * @param int		$mediatypes['status']				media type status
	 * @param int		$mediatypes['maxsessions']			Limit of simultaneously processed alerts.
	 * @param int		$mediatypes['maxattempts']			Maximum attempts to deliver alert successfully.
	 * @param string	$mediatypes['attempt_interval']		Interval between alert delivery attempts.
	 * @param string    $mediatypes['webhook']              Webhook javascript body.
	 * @param string    $mediatypes['timeout']              Webhook javascript HTTP request timeout.
	 * @param string    $mediatypes['receive_tags']         Webhook HTTP response should be parsed as tags.
	 * @param string    $mediatypes['url']                  Webhook additional info in frontend, supports received tags.
	 * @param string    $mediatypes['url_name']	            Webhook 'url' visual name.
	 * @param string    $mediatypes['description']          Media type description.
	 *
	 * @return array
	 */
	public function create($mediatypes) {
		$mediatypes = zbx_toArray($mediatypes);

		$this->validateCreate($mediatypes);

		$mediatypeids = DB::insert('media_type', $mediatypes);
		$webhook_params = [];

		foreach ($mediatypes as $i => &$mediatype) {
			$mediatypeid = $mediatypeids[$i];
			$mediatype['mediatypeid'] = $mediatypeid;

			if ($mediatype['type'] == MEDIA_TYPE_WEBHOOK && array_key_exists('params', $mediatype)) {
				foreach ($mediatype['params'] as $param) {
					$webhook_params[] = compact('mediatypeid') + $param;
				}
			}
		}
		unset($mediatype);

		if ($webhook_params) {
			DB::insertBatch('media_type_param', $webhook_params);
		}

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_MEDIA_TYPE, $mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * Update Media types.
	 *
	 * @param array		$mediatypes							multidimensional array with media types data
	 * @param int		$mediatypes['mediatypeid']			id
	 * @param int		$mediatypes['type']					type
	 * @param string	$mediatypes['name']
	 * @param string	$mediatypes['smtp_server']			SMTP server
	 * @param int		$mediatypes['smtp_port']			SMTP port
	 * @param string	$mediatypes['smtp_helo']			SMTP hello
	 * @param string	$mediatypes['smtp_email']			SMTP email
	 * @param int		$mediatypes['smtp_security']		SMTP connection security
	 * @param int		$mediatypes['smtp_verify_peer']		SMTP verify peer
	 * @param int		$mediatypes['smtp_verify_host']		SMTP verify host
	 * @param int		$mediatypes['smtp_authentication']	SMTP authentication
	 * @param int		$mediatypes['content_type']			Message format
	 * @param string	$mediatypes['exec_path']			script name/message text limit
	 * @param string	$mediatypes['exec_params']			script parameters
	 * @param string	$mediatypes['gsm_modem']			GSM modem
	 * @param string	$mediatypes['username']				username
	 * @param string	$mediatypes['passwd']				password
	 * @param int		$mediatypes['status']				media type status
	 * @param int		$mediatypes['maxsessions']			Limit of simultaneously processed alerts.
	 * @param int		$mediatypes['maxattempts']			Maximum attempts to deliver alert successfully.
	 * @param string	$mediatypes['attempt_interval']		Interval between alert delivery attempts.
	 * @param string    $mediatypes['webhook']              Webhook javascript body.
	 * @param string    $mediatypes['timeout']              Webhook javascript HTTP request timeout.
	 * @param string    $mediatypes['receive_tags']         Webhook HTTP response should be parsed as tags.
	 * @param string    $mediatypes['url']                  Webhook additional info in frontend, supports received tags.
	 * @param string    $mediatypes['url_name']	            Webhook 'url' visual name.
	 * @param string    $mediatypes['description']          Media type description.
	 *
	 * @return array
	 */
	public function update($mediatypes) {
		$mediatypes = zbx_toArray($mediatypes);

		$this->validateUpdate($mediatypes);

		$update = [];
		$webhooks_params = [];
		$default_values = DB::getDefaults('media_type');
		$db_mediatypes = DB::select('media_type', [
			'output' => ['mediatypeid', 'type', 'name', 'smtp_server', 'smtp_helo', 'smtp_email', 'exec_path',
				'gsm_modem', 'username', 'passwd', 'status', 'smtp_port', 'smtp_security', 'smtp_verify_peer',
				'smtp_verify_host', 'smtp_authentication', 'exec_params', 'maxsessions', 'maxattempts',
				'attempt_interval', 'content_type', 'webhook', 'timeout', 'receive_tags', 'url', 'url_name',
				'description'
			],
			'filter' => ['mediatypeid' => zbx_objectValues($mediatypes, 'mediatypeid')],
			'preservekeys' => true
		]);

		$type_switch_fields = [
			MEDIA_TYPE_EMAIL => [
				'smtp_server', 'smtp_helo', 'smtp_email', 'smtp_port', 'smtp_security', 'smtp_verify_peer',
				'smtp_verify_host', 'smtp_authentication', 'passwd', 'username', 'content_type'
			],
			MEDIA_TYPE_EXEC => [
				'exec_path', 'exec_params'
			],
			MEDIA_TYPE_SMS => [
				'gsm_modem'
			],
			MEDIA_TYPE_WEBHOOK => [
				'webhook', 'timeout', 'receive_tags', 'url', 'url_name', 'params'
			]
		];
		$default_values['params'] = [];

		foreach ($mediatypes as $mediatype) {
			$mediatypeid = $mediatype['mediatypeid'];
			$db_type = $db_mediatypes[$mediatypeid]['type'];
			$type = array_key_exists('type', $mediatype) ? $mediatype['type'] : $db_type;
			unset($mediatype['mediatypeid']);

			if ($type == MEDIA_TYPE_WEBHOOK) {
				if (array_key_exists('receive_tags', $mediatype)
						&& $mediatype['receive_tags'] == MEDIA_TYPE_TAGS_DISABLED) {
					$mediatype = [
						'url' => $default_values['url'],
						'url_name' => $default_values['url_name'],
					] + $mediatype;
				}

				if (array_key_exists('params', $mediatype)) {
					$params = [];

					foreach ($mediatype['params'] as $param) {
						$params[$param['name']] = $param['value'];
					};

					$webhooks_params[$mediatypeid] = $params;
					unset($mediatype['params']);
				}
			}

			if ($type != $db_type) {
				$mediatype = array_intersect_key($default_values,
					array_fill_keys($type_switch_fields[$db_type], '')) + $mediatype;
			}

			if (!empty($mediatype)) {
				$update[] = [
					'values' => $mediatype,
					'where' => ['mediatypeid' => $mediatypeid]
				];
			}
		}

		DB::update('media_type', $update);
		$mediatypeids = zbx_objectValues($mediatypes, 'mediatypeid');

		if ($webhooks_params) {
			$ins_webhook_params = [];
			$del_webhook_params = [];
			$upd_webhook_params = [];
			$db_webhooks_params = DB::select('media_type_param', [
				'output' => ['mediatype_paramid', 'mediatypeid', 'name', 'value'],
				'filter' => ['mediatypeid' => array_keys($webhooks_params)]
			]);

			foreach ($db_webhooks_params as $param) {
				$mediatypeid = $param['mediatypeid'];

				if (!array_key_exists($mediatypeid, $webhooks_params)) {
					$del_webhook_params[] = $param['mediatype_paramid'];
				}
				elseif (!array_key_exists($param['name'], $webhooks_params[$mediatypeid])) {
					$del_webhook_params[] = $param['mediatype_paramid'];
				}
				elseif ($webhooks_params[$mediatypeid][$param['name']] !== $param['value']) {
					$upd_webhook_params[] = [
						'values' => ['value' => $webhooks_params[$mediatypeid][$param['name']]],
						'where' => ['mediatype_paramid' => $param['mediatype_paramid']]
					];
					unset($webhooks_params[$mediatypeid][$param['name']]);
				}
				else {
					unset($webhooks_params[$mediatypeid][$param['name']]);
				}
			}

			$webhooks_params = array_filter($webhooks_params);

			foreach ($webhooks_params as $mediatypeid => $params) {
				foreach ($params as $name => $value) {
					$ins_webhook_params[] = compact('mediatypeid', 'name', 'value');
				}
			}

			if ($del_webhook_params) {
				DB::delete('media_type_param', ['mediatype_paramid' => array_keys(array_flip($del_webhook_params))]);
			}

			if ($upd_webhook_params) {
				DB::update('media_type_param', $upd_webhook_params);
			}

			if ($ins_webhook_params) {
				DB::insert('media_type_param', $ins_webhook_params);
			}
		}

		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_MEDIA_TYPE, $mediatypes, $db_mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * Delete Media types.
	 *
	 * @param array $mediatypeids
	 *
	 * @return array
	 */
	public function delete(array $mediatypeids) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete media types.'));
		}

		$actions = API::Action()->get([
			'mediatypeids' => $mediatypeids,
			'output' => API_OUTPUT_EXTEND,
			'preservekeys' => true
		]);
		if (!empty($actions)) {
			$action = reset($actions);
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Media types used by action "%s".', $action['name']));
		}

		$db_mediatypes = DB::select('media_type', [
			'output' => ['mediatypeid', 'name'],
			'filter' => ['mediatypeid' => $mediatypeids],
			'preservekeys' => true
		]);

		DB::delete('media_type', ['mediatypeid' => $mediatypeids]);
		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_MEDIA_TYPE, $db_mediatypes);

		return ['mediatypeids' => $mediatypeids];
	}

	/**
	 * Check required fields by type. Values for fields must not be empty.
	 *
	 * @param array		$mediatype							An array of media type data.
	 * @param string	$mediatype['name']					Name of the media type.
	 * @param string	$mediatype['type']					E-mail, Script and SMS.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function checkRequiredFieldsByType(array $mediatype) {
		$type_validator = new CLimitedSetValidator([
			'values' => array_keys(media_type2str())
		]);

		if (!$type_validator->validate($mediatype['type'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s(
				'Incorrect value "%1$s" in field "%2$s" for media type "%3$s".',
				$mediatype['type'],
				'type',
				$mediatype['name']
			));
		}

		$required_fields_by_type = [
			MEDIA_TYPE_EMAIL => ['smtp_server', 'smtp_helo', 'smtp_email'],
			MEDIA_TYPE_EXEC => ['exec_path'],
			MEDIA_TYPE_WEBHOOK => [],
			MEDIA_TYPE_SMS => ['gsm_modem']
		];

		foreach ($required_fields_by_type[$mediatype['type']] as $field) {
			// Check if fields set on Create method. For update method they are checked when type is changed.
			if (!array_key_exists($field, $mediatype)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Field "%1$s" is required for media type "%2$s".', $field, $mediatype['name'])
				);
			}
			elseif (array_key_exists($field, $mediatype)
					&& ($mediatype[$field] === '' || $mediatype[$field] === null)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Field "%1$s" is missing a value for media type "%2$s".', $field, $mediatype['name'])
				);
			}
		}
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		// adding users
		if ($options['selectUsers'] !== null && $options['selectUsers'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'mediatypeid', 'userid', 'media');
			$users = API::User()->get([
				'output' => $options['selectUsers'],
				'userids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $users, 'users');
		}

		if ($this->outputIsRequested('params', $options['output'])) {
			foreach ($result as &$mediatype) {
				$mediatype['params'] = [];
			}
			unset($mediatype);

			$mediatype_params = DB::select('media_type_param', [
				'output' => ['mediatypeid', 'name', 'value'],
				'filter' => ['mediatypeid' => array_keys($result)]
			]);

			foreach ($mediatype_params as $param) {
				$result[$param['mediatypeid']]['params'][] = [
					'name' => $param['name'],
					'value' => $param['value']
				];
			}
		}

		return $result;
	}

	/**
	 * Get media type validation rules, return array where validation rules are store in 'fields' key and media type
	 * specific validation rules are stored in key having value of media type 'type' field.
	 *
	 * @return array
	 */
	protected function getValidationRules() {
		return [
			'fields' => [
				'description' => [
					'type' => API_STRING_UTF8,
					'length' => DB::getFieldLength('media_type', 'description')
				]
			],
			MEDIA_TYPE_WEBHOOK => [
				'webhook' => [
					'type' => API_STRING_UTF8,
					'flags' => API_REQUIRED | API_NOT_EMPTY,
					'length' => DB::getFieldLength('media_type', 'webhook')
				],
				'timeout' => [
					'type' => API_TIME_UNIT,
					'length' => DB::getFieldLength('media_type', 'timeout'),
					'in' => '1:60'
				],
				'receive_tags' => [
					'type' => API_INT32,
					'in' => implode(',', [MEDIA_TYPE_TAGS_DISABLED, MEDIA_TYPE_TAGS_ENABLED])
				],
				'url' => [
					// Should be checked as string not as url because it can contain maros tags.
					'type' => API_STRING_UTF8,
					'flags' => API_NOT_EMPTY,
					'length' => DB::getFieldLength('media_type', 'url')
				],
				'url_name' => [
					'type' => API_STRING_UTF8,
					'flags' => API_NOT_EMPTY,
					'length' => DB::getFieldLength('media_type', 'url_name')
				],
				'params' => [
					'type' => API_OBJECTS,
					'fields' => [
						'name' => [
							'type' => API_STRING_UTF8,
							'flags' => API_REQUIRED | API_NOT_EMPTY,
							'length' => DB::getFieldLength('media_type_param', 'name')
						],
						'value' => [
							'type' => API_STRING_UTF8,
							'flags' => API_REQUIRED,
							'length' => DB::getFieldLength('media_type_param', 'value')
						]
					]
				]
			]
		];
	}
}
