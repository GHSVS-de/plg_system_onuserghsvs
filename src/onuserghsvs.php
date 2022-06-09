<?php
defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

class PlgSystemOnUserGhsvs extends CMSPlugin
{
	/**
	 * Application object
	 *
	 * @var    \Joomla\CMS\Application\CMSApplication
	 * @since  4.0.0
	 */
	protected $app;

	/**
	 * Database driver
	 *
	 * @var    \Joomla\Database\DatabaseInterface
	 * @since  4.0.0
	 */
	protected $db;

	/**
	 * Load plugin language files automatically
	 *
	 * @var    boolean
	 * @since  3.6.3
	 */
	protected $autoloadLanguage = true;

	public function onUserAfterDelete($user, $success, $msg): void
	{
	}

	public function onUserAfterDeleteGroup($group, $success, $msg): void
	{
	}

	public function onUserAfterLogin($options)
	{
	}

	public function onUserAfterRemind($user)
	{
	}

	/**
	 * After store user method.
	 *
	 * Method is called after user data is stored in the database.
	 *
	 * @param   array    $user     Holds the new user data.
	 * @param   boolean  $isNew    True if a new user is stored.
	 * @param   boolean  $success  True if user was successfully stored in the database.
	 * @param   string   $msg      Message.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function onUserAfterSave($user, $isNew, $success, $msg): void
	{
		// If the user wasn't stored.
		if (!$success || $this->app->getDocument()->getType() !== 'html')
		{
			return;
		}

		if (
			!$isNew
			|| !$this->app->isClient('administrator')
			|| $this->params->get('informAdmins', 1) === 0
		) {
			return;
		}

		// Let's find out the email addresses to notify
		$superUsers    = [];
		$specificEmails = $this->params->get('specificEmails', '');

		if (!empty($specificEmails))
		{
			$superUsers = $this->getSuperUsers($specificEmails);
		}

		if (empty($superUsers))
		{
			$superUsers = $this->getSuperUsers();
		}

		if (empty($superUsers))
		{
			return;
		}

		$mailFrom = $this->app->get('mailfrom');
		$fromName = $this->app->get('fromname');

		// Compute the mail subject.
		$emailSubject = Text::sprintf(
			'PLG_SYSTEM_ONUSERGHSVS_NEW_USER_EMAIL_SUBJECT',
			$user['name'],
			Uri::root()
		);

		// Compute the mail body.
		$emailBody = Text::sprintf(
			'PLG_SYSTEM_ONUSERGHSVS_NEW_USER_EMAIL_BODY',
			Uri::root(),
			$this->app->get('sitename'),
			$user['name'],
			$user['email'],
			$user['username'],
			$user['password_clear']
		);

		// Send the emails to the Super Users
		foreach ($superUsers as $superUser)
		{
			$mailer = Factory::getMailer();
			$mailer->setSender([$mailFrom, $fromName]);
			$mailer->addRecipient($superUser->email);
			$mailer->setSubject($emailSubject);
			$mailer->setBody($emailBody);
			$mailer->Send();
		}
	}

	public function onUserAfterSaveGroup($context, $table, $isNew): void
	{
	}

	public function onUserAuthenticate($credentials, $options, &$response)
	{
	}

	public function onUserBeforeSave($oldUser, $isNew, $newUser)
	{
		if ($this->app->getDocument()->getType() !== 'html')
		{
			return;
		}

		if (
			$isNew && $this->app->isClient('site')
			&& ($this->params->get('filterNameOnSave', 0) === 1)
		) {
			$rules = trim($this->params->get('filterNameOnSaveRules', ''));

			if ($rules === '')
			{
				return;
			}

			$replaceWhat = [
			"\n\r",
			"\r\n",
			"\r",
		];
			$rules = str_replace($replaceWhat, "\n", $rules);
			$rules = array_map('trim', explode("\n", $rules));

			foreach ($rules as $value)
			{
				if (!$value)
				{
					continue;
				}

				if (mb_stripos($newUser['name'], $value) !== false)
				{
					$this->app->enqueueMessage(
						Text::_('PLG_SYSTEM_ONUSERGHSVS_FILTERNAMEONSAVE_MESSAGE'),
						'danger'
					);

					return false;
				}
			}
		}

		if (!$isNew && $this->params->get('blockUserSaving', 0) === 1)
		{
			$blockedUsers = $this->params->get('users_to_block', [], 'array');

			if (in_array($oldUser['id'], $blockedUsers))
			{
				$feBlock = $this->app->isClient('site')
					&& $this->params->get('block_fe', 0) === 1;
				$beBlock = $this->app->isClient('administrator')
					&& $this->params->get('block_be', 0) === 1;

				if (version_compare(JVERSION, '4', 'lt'))
				{
					$isSuperAdmin = Factory::getUser()->authorise(
						'core.admin',
						'com_users'
					);
				}
				else
				{
					$isSuperAdmin = $this->app->getIdentity()->authorise(
						'core.admin',
						'com_users'
					);
				}

				if ($feBlock || $beBlock)
				{
					if (
						!$isSuperAdmin
						|| ($isSuperAdmin && $this->params->get('allow_admins', 0) !== 1)
					) {
						$this->app->enqueueMessage(
							Text::_('PLG_ONUSERGHSVS_BLOCKUSERSAVING_MESSAGE'),
							'danger'
						);

						return false;
					}
				}
			}
		}
	}

	public function onUserLoginFailure($response)
	{
	}

	public function onUserLogout($user, $options = [])
	{
	}

	/**
	 * Returns the Super Users email information. If you provide a comma separated $email list
	 * we will check that these emails do belong to Super Users and that they have not blocked
	 * system emails.
	 *
	 * @copyright This method is a copy from Joomla's core plugin updatenotification. Modifications by ghsvs.de will no longer reflect the original work of its authors.
	 *
	 * @param   null|string  $email  A list of Super Users to email
	 *
	 * @return  array  The list of Super User emails
	 *
	 * @since   3.5
	 */
	private function getSuperUsers($email = null)
	{
		$db = $this->db;

		// Convert the email list to an array
		if (!empty($email))
		{
			$temp   = explode(',', $email);
			$emails = [];

			foreach ($temp as $entry)
			{
				$entry    = trim($entry);
				$emails[] = $db->q($entry);
			}

			$emails = array_unique($emails);
		}
		else
		{
			$emails = [];
		}

		// Get a list of groups which have Super User privileges
		$ret = [];

		try
		{
			$rootId    = Table::getInstance('Asset', 'JTable')->getRootId();
			$rules     = Access::getAssetRules($rootId)->getData();
			$rawGroups = $rules['core.admin']->getData();
			$groups    = [];

			if (empty($rawGroups))
			{
				return $ret;
			}

			foreach ($rawGroups as $g => $enabled)
			{
				if ($enabled)
				{
					$groups[] = $db->q($g);
				}
			}

			if (empty($groups))
			{
				return $ret;
			}
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		// Get the user IDs of users belonging to the SA groups
		try
		{
			$query = $db->getQuery(true)
				->select($db->qn('user_id'))
				->from($db->qn('#__user_usergroup_map'))
				->where($db->qn('group_id') . ' IN(' . implode(',', $groups) . ')');
			$db->setQuery($query);
			$rawUserIDs = $db->loadColumn(0);

			if (empty($rawUserIDs))
			{
				return $ret;
			}

			$userIDs = [];

			foreach ($rawUserIDs as $id)
			{
				$userIDs[] = $db->q($id);
			}
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		// Get the user information for the Super Administrator users
		try
		{
			$query = $db->getQuery(true)
				->select(
					[
						$db->qn('id'),
						$db->qn('username'),
						$db->qn('email'),
					]
				)->from($db->qn('#__users'))
				->where($db->qn('id') . ' IN(' . implode(',', $userIDs) . ')')
				->where($db->qn('block') . ' = 0')
				->where($db->qn('sendEmail') . ' = ' . $db->q('1'));

			if (!empty($emails))
			{
				$query->where('LOWER(' . $db->qn('email') . ') IN(' . implode(',', array_map('strtolower', $emails)) . ')');
			}

			$db->setQuery($query);
			$ret = $db->loadObjectList();
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		return $ret;
	}
}
