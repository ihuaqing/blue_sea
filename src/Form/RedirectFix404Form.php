<?php

/**
 * @file
 * Contains \Drupal\redirect\Form\RedirectFix404Form
 */

namespace Drupal\redirect\Form;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

class RedirectFix404Form extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'redirect_fix_404_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $destination = $this->getDestinationArray();

    $search = $this->getRequest()->get('search');
    $form['#attributes'] = array('class' => array('search-form'));

    $form['basic'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Filter 404s'),
      '#attributes' => array('class' => array('container-inline')),
    );
    $form['basic']['filter'] = array(
      '#type' => 'textfield',
      '#title' => '',
      '#default_value' => $search,
      '#maxlength' => 128,
      '#size' => 25,
    );
    $form['basic']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#action' => 'filter',
    );
    if ($search) {
      $form['basic']['reset'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#action' => 'reset',
      );
    }

    $languages = \Drupal::languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    $multilingual = \Drupal::languageManager()->isMultilingual();

    $header = array(
      array('data' => $this->t('Path'), 'field' => 'path'),
      array('data' => $this->t('Count'), 'field' => 'count', 'sort' => 'desc'),
      array('data' => $this->t('Last accessed'), 'field' => 'timestamp'),
    );
    if ($multilingual) {
      $header[] = array('data' => $this->t('Language'), 'field' => 'language');
    }
    $header[] = array('data' => $this->t('Operations'));

    $query = \Drupal::database()
      ->select('redirect_404', 'r404')
      ->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header)
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(25)
      ->fields('r404');

    if ($search) {
      // Replace wildcards with PDO wildcards.
      $wildcard = '%' . trim(preg_replace('!\*+!', '%', \Drupal::database()->escapeLike($search)), '%') . '%';
      $query->condition('path', $wildcard, 'LIKE');
    }
    $result = $query->execute();

    $rows = array();
    foreach ($result as $row) {
      $request = Request::create($row->path, 'GET', [], [], [], \Drupal::request()->server->all());
      $path = ltrim($request->getPathInfo(), '/');

      $row = array();
      $row['source'] = Link::fromTextAndUrl($row->path, Url::fromUri('base:' . $path, array('query' => $destination)));
      $row['count'] = $row->count;
      $row['timestamp'] = \Drupal::service('date.formatter')->format($row->timestamp, 'short');
      if ($multilingual) {
        if (isset($languages[$row->langcode])) {
          $row['language'] =$languages[$row->langcode]->getName();
        }
        else {
          $row['language'] =$this->t('Undefined @langcode', array('@langcode' => $row->langcode));
        }
      }

      $operations = array();
      if (\Drupal::entityTypeManager()->getAccessControlHandler('redirect')->createAccess()) {
        $operations['add'] = array(
          'title' =>$this->t('Add redirect'),
          'url' => Url::fromRoute('redirect.add', [], ['query' => array('source' => $path, 'language' => $row->langcode) + $destination]),
        );
      }
      $row['operations'] = array(
        'data' => array(
          '#theme' => 'operations',
          '#links' => $operations,
        ),
      );

      $rows[] = $row;
    }

    $form['redirect_404_table']  = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' =>$this->t('No 404 pages without redirects found.'),
    );
    $form['redirect_404_pager'] = array('#type' => 'pager');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if ($form_state->getTriggeringElement()['#action'] == 'filter') {
      $form_state->setRedirect('redirect.fix_404', array(), array('query' => array('search' => trim($form_state->getValue('filter')))));
    }
    else {
      $form_state->setRedirect('redirect.fix_404');
    }
  }

}
