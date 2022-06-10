<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
JFormHelper::loadFieldClass('list');

class JFormFieldUsersGhsvs extends JFormFieldList
{
	public $type = 'UsersGhsvs';

	protected static $options = [];

	protected $layout;

	protected function getOptions()
	{
		$hash = md5($this->element);

		if (!isset(static::$options[$hash]))
		{
			static::$options[$hash] = parent::getOptions();

			$options = [];

			if (version_compare(JVERSION, '4', 'lt'))
			{
				$db = Factory::getDbo();
			}
			else
			{
				$db = Factory::getContainer()->get('DatabaseDriver');
			}

			$query = $db->getQuery(true)
				->select(
					[
						$db->quoteName('u.id', 'value'),
						$db->quoteName('u.name', 'text'),
						$db->quoteName('u.username', 'username'),
					]
				)
				->from($db->quoteName('#__users', 'u'))
				->group(
					[
						$db->quoteName('u.id'),
						$db->quoteName('u.name'),
					]
				)
				->order($db->quoteName('u.name'));

			$db->setQuery($query);

			if ($options = $db->loadObjectList())
			{
				foreach ($options as $option)
				{
					$option->text .= ' (' . $option->username . ')';
				}

				static::$options[$hash] = array_merge(static::$options[$hash], $options);
			}
		}

		return static::$options[$hash];
	}

	protected function getInput()
	{
		if (version_compare(JVERSION, '4', 'lt'))
		{
		}
		else
		{
			$this->layout = 'joomla.form.field.list-fancy-select';
		}

		return parent::getInput();
	}
}
