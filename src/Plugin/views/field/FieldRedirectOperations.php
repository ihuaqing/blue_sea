<?php

/**
 * @file
 * Contains Drupal\redirect\Plugin\views\field\FieldRedirectOperations.
 */

namespace Drupal\redirect\Plugin\views\field;

use Drupal\redirect\Entity\Redirect;
use Drupal\views\Plugin\views\field\FieldPluginBase;

class FieldRedirectOperations extends FieldPluginBase {
  function construct() {
    parent::construct();
    $this->additional_fields['rid'] = 'rid';
  }

  function option_definition() {
    $options = parent::option_definition();
    $options['edit_text'] = array('default' => '', 'translatable' => TRUE);
    $options['delete_text'] = array('default' => '', 'translatable' => TRUE);
    return $options;
  }

  function options_form(&$form, &$form_state) {
    parent::options_form($form, $form_state);
    $form['edit_text'] = array(
      '#type' => 'textfield',
      '#title' => t('Text to display for edit links'),
      '#default_value' => $this->options['edit_text'],
    );
    $form['delete_text'] = array(
      '#type' => 'textfield',
      '#title' => t('Text to display for delete links'),
      '#default_value' => $this->options['delete_text'],
    );
  }

  function query() {
    $this->ensure_my_table();
    $this->add_additional_fields();
  }

  function render($values) {
    $rid = $values->{$this->aliases['rid']};
    $redirect = Redirect::load($rid);
    $destination = drupal_get_destination();

    $operations = array();
    if ($redirect->access('update')) {
      $operations['edit'] = array(
        'title' => !empty($this->options['edit_text']) ? $this->options['edit_text'] : t('Edit'),
        'href' => 'admin/config/search/redirect/edit/' . $rid,
        'query' => $destination,
      );
    }
    if ($redirect->access('delete')) {
      $operations['delete'] = array(
        'title' => !empty($this->options['delete_text']) ? $this->options['delete_text'] : t('Delete'),
        'href' => 'admin/config/search/redirect/delete/' . $rid,
        'query' => $destination,
      );
    }

    if (!empty($operations)) {
      return theme('links', array('links' => $operations, 'attributes' => array('class' => array('links', 'inline', 'nowrap'))));
    }
    else {
      return '';
    }
  }
}
