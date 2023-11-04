<?php
namespace GHSVS\Plugin\System\OnUserGhsvs\Extension;

\defined('_JEXEC') or die;

use Exception;
use Joomla\Application\ApplicationInterface;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Form\Form;
use Joomla\Input\Input;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\DispatcherInterface;

final class OnUserGhsvs extends CMSPlugin
{
	use DatabaseAwareTrait;
	# use UserFactoryAwareTrait;

	/**
	 * Application object
	 *
	 * @var    \Joomla\CMS\Application\CMSApplication
	 * @since  4.0.0
	 */
	private $app;

	private $input;

	/**
	 * Load plugin language files automatically
	 *
	 * @var    boolean
	 * @since  3.6.3
	 */
	protected $autoloadLanguage = true;

	public function __construct(
		DispatcherInterface $dispatcher,
		array $config,
		ApplicationInterface $app = null,
		Input $jinput = null,
	) {
		parent::__construct($dispatcher, $config);
		$this->app = $app;
		$this->input = $jinput;
	}

	public function onContentPrepareForm(Form $form, $data)
	{
		$extension = 'com_users';

		if (
			$this->params->get('passwordMinimumLength', 0) === 1
			&& $this->app->isClient('administrator')
			&& $this->input->get('option', '') === 'com_config'
			&& ($this->input->get('view', '') === 'component' || $this->input->get('controller', '') === 'component')
			&& $this->input->get('component', '') === $extension
		) {
			$min = (int) $this->params->get('minimum_length', 5);
			$group = null;
			$form->setFieldAttribute('minimum_length', 'min', $min, $group);
		}
	}

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
		$specificEmails = trim($this->params->get('specificEmails', ''));

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

	/**
		* Method is called before user data is stored in the database
		*
		* @param   array    $oldUser   Holds the old user data.
		* @param   boolean  $isNew  True if a new user is stored.
		* @param   array    $newUser   Holds the new user data.
		*
		* @return  boolean (???) mixed (???)
		*/
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

			return true;
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

				// -1:only super users, 1:authorized admins
				$allow_admins = $this->params->get('allow_admins', -1);
				$isAuthorized = 0;

				if ($allow_admins !== 0)
				{
					$isAuthorized = $this->app->getIdentity()->authorise(
						'core.admin',
						$allow_admins > 0 ? 'com_users' : null
					);
				}

				if (($feBlock || $beBlock) && !$isAuthorized)
				{
					$this->app->enqueueMessage(
						Text::_('PLG_ONUSERGHSVS_BLOCKUSERSAVING_MESSAGE'),
						'danger'
					);

					return false;
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
		$db = $this->getDatabase();
		$emails = [];

		// Convert the email list to an array
		if (!empty($email)) {
			$temp = explode(',', $email);

			foreach ($temp as $entry) {
					$emails[] = trim($entry);
			}

			$emails = array_unique($emails);
		}

		// Get a list of groups which have Super User privileges
		$ret = [];

		try {
			$rootId    = Table::getInstance('Asset')->getRootId();
			$rules     = Access::getAssetRules($rootId)->getData();
			$rawGroups = $rules['core.admin']->getData();
			$groups    = [];

			if (empty($rawGroups)) {
				return $ret;
			}

			foreach ($rawGroups as $g => $enabled) {
				if ($enabled) {
					$groups[] = $g;
				}
			}

			if (empty($groups)) {
				return $ret;
			}
		} catch (\Exception $exc) {
			return $ret;
		}

		// Get the user IDs of users belonging to the SA groups
		try {
			$query = $db->getQuery(true)
			->select($db->quoteName('user_id'))
			->from($db->quoteName('#__user_usergroup_map'))
			->whereIn($db->quoteName('group_id'), $groups);

			$db->setQuery($query);
			$userIDs = $db->loadColumn(0);

			if (empty($userIDs)) {
				return $ret;
			}
		} catch (\Exception $exc) {
			return $ret;
		}

		// Get the user information for the Super Administrator users
		try {
			$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'username', 'email']))
			->from($db->quoteName('#__users'))
			->whereIn($db->quoteName('id'), $userIDs)
			->where($db->quoteName('block') . ' = 0')
			->where($db->quoteName('sendEmail') . ' = 1');

			if (!empty($emails)) {
				$lowerCaseEmails = array_map('strtolower', $emails);
				$query->whereIn('LOWER(' . $db->quoteName('email') . ')', $lowerCaseEmails, ParameterType::STRING);
			}

			$db->setQuery($query);
			$ret = $db->loadObjectList();
		} catch (\Exception $exc) {
			return $ret;
		}

		return $ret;
	}
}
