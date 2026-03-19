<?php

return array (
  0 =>
  array (
    'name' => 'base',
    'title' => '基础配置',
    'type' => 'array',
    'content' =>
    array (
    ),
    'value' =>
    array (
      'email' => '',
      'filter_controller' => 'test',
      'filter_action' => '',
      'filter_url' => '',
    ),
    'rule' => 'required',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
  ),
  1 =>
  array (
    'name' => 'time',
    'title' => '响应时间',
    'type' => 'array',
    'content' =>
    array (
    ),
    'value' =>
    array (
      'pl' => '1800',
      'sj' => '5000',
      'zb' => '10',
      'open' => '0',
    ),
    'rule' => 'required',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
  ),
  2 =>
  array (
    'name' => 'error',
    'title' => '错误',
    'type' => 'array',
    'content' =>
    array (
    ),
    'value' =>
    array (
      'pl' => '1800',
      'sj' => '500',
      'zb' => '10',
      'open' => '0',
    ),
    'rule' => 'required',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
  ),
  3 =>
  array (
    'name' => 'ip',
    'title' => 'ip',
    'type' => 'array',
    'content' =>
    array (
    ),
    'value' =>
    array (
      'pl' => '1800',
      'white' => '127.0.0.1',
      'zb' => '20',
      'open' => '0',
    ),
    'rule' => 'required',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
  ),
  4 =>
  array (
    'name' => 'count',
    'title' => '最大请求',
    'type' => 'array',
    'content' =>
    array (
    ),
    'value' =>
    array (
      'pl' => '300',
      'max' => '500',
      'open' => '0',
    ),
    'rule' => 'required',
    'msg' => '',
    'tip' => '',
    'ok' => '',
    'extend' => '',
  ),
);
