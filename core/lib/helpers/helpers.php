<?php

//функции https://github.com/laravel/framework/blob/5.2/src/Illuminate/Foundation/helpers.php

/**
 * Возвращает экземпляр основного объекта системы. Если его не существует, создаёт его.
 * Является обёрткой для паттерна Singletone
 * Если указан необязательный параметр, возвращет свойство основного объекта с указанными именем
 * Например: d('title') или d('User')
 * Более короткая запись функции doit()
 *
 * @param string $object (необязательно) Свойство основного объекта
 * @return doitClass Экземпляр основного объекта системы
 */
function d()
{
	return Doit::$instance;
}