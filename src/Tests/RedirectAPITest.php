<?php

/**
 * @file
 * Contains \Drupal\redirect\Tests\RedirectAPITest.
 */

namespace Drupal\redirect\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\redirect\Entity\Redirect;
use Drupal\Core\Language\Language;
use Drupal\simpletest\KernelTestBase;

/**
 * Redirect entity and redirect API test coverage.
 *
 * @group redirect
 */
class RedirectAPITest extends KernelTestBase {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $controller;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('redirect', 'link', 'field', 'system', 'user', 'language');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('redirect');
    $this->installEntitySchema('user');
    $this->installConfig(array('redirect'));

    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();

    $this->controller = $this->container->get('entity.manager')->getStorage('redirect');
  }

  /**
   * Test redirect entity logic.
   */
  public function testRedirectEntity() {
    // Create a redirect and test if hash has been generated correctly.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->controller->create();
    $redirect->setSource('some-url', array('key' => 'val'));

    $redirect->save();
    $this->assertEqual(Redirect::generateHash('some-url', array('key' => 'val'), Language::LANGCODE_NOT_SPECIFIED), $redirect->getHash());
    // Update the redirect source query and check if hash has been updated as
    // expected.
    $redirect->setSource('some-url', array('key1' => 'val1'));
    $redirect->save();
    $this->assertEqual(Redirect::generateHash('some-url', array('key1' => 'val1'), Language::LANGCODE_NOT_SPECIFIED), $redirect->getHash());
    // Update the redirect source path and check if hash has been updated as
    // expected.
    $redirect->setSource('another-url', array('key1' => 'val1'));
    $redirect->save();
    $this->assertEqual(Redirect::generateHash('another-url', array('key1' => 'val1'), Language::LANGCODE_NOT_SPECIFIED), $redirect->getHash());
    // Update the redirect language and check if hash has been updated as
    // expected.
    $redirect->setLanguage('de');
    $redirect->save();
    $this->assertEqual(Redirect::generateHash('another-url', array('key1' => 'val1'), 'de'), $redirect->getHash());
    // Create a few more redirects to test the select.
    for ($i = 0; $i < 5; $i++) {
      $redirect = $this->controller->create();
      $redirect->setSource($this->randomMachineName());
      $redirect->save();
    }
    /** @var \Drupal\redirect\RedirectRepository $repository */
    $repository = \Drupal::service('redirect.repository');
    $redirect = $repository->findMatchingRedirect('another-url', array('key1' => 'val1'), 'de');
    if (!empty($redirect)) {
      $this->assertEqual($redirect->getSourceUrl(), '/another-url?key1=val1');
    }
    else {
      $this->fail(t('Failed to find matching redirect.'));
    }

    // Load the redirect based on url.
    $redirects = $repository->findBySourcePath('another-url');
    $redirect = array_shift($redirects);
    if (!empty($redirect)) {
      $this->assertEqual($redirect->getSourceUrl(), '/another-url?key1=val1');
    }
    else {
      $this->fail(t('Failed to find redirect by source path.'));
    }

    // Test passthrough_querystring.
    $redirect->setSource('a-different-url');
    $redirect->save();
    $redirect = $repository->findMatchingRedirect('a-different-url', ['key1' => 'val1'], 'de');
    if (!empty($redirect)) {
      $this->assertEqual($redirect->getSourceUrl(), '/a-different-url');
    }
    else {
      $this->fail('Failed to find redirect by source path with query string.');
    }

    // Add another redirect to the same path, with a query. This should always
    // be found before the source without a query set.
    /** @var \Drupal\redirect\Entity\Redirect $new_redirect */
    $new_redirect = $this->controller->create();
    $new_redirect->setSource('a-different-url', ['foo' => 'bar']);
    $new_redirect->save();
    $found = $repository->findMatchingRedirect('a-different-url', ['foo' => 'bar'], 'de');
    if (!empty($found)) {
      $this->assertEqual($found->getSourceUrl(), '/a-different-url?foo=bar');
    }
    else {
      $this->fail('Failed to find a redirect by source path with query string.');
    }

    // Hashes should be case-insensitive since the source paths are.
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->controller->create();
    $redirect->setSource('Case-Sensitive-Path');
    $redirect->save();
    $found = $repository->findBySourcePath('case-sensitive-path');
    if (!empty($found)) {
      $found = reset($found);
      $this->assertEqual($found->getSourceUrl(), '/Case-Sensitive-Path');
    }
    else {
      $this->fail('findBySourcePath is case sensitive');
    }
    $found = $repository->findMatchingRedirect('case-sensitive-path');
    if (!empty($found)) {
      $this->assertEqual($found->getSourceUrl(), '/Case-Sensitive-Path');
    }
    else {
      $this->fail('findMatchingRedirect is case sensitive.');
    }
  }

  /**
   * Test redirect_sort_recursive().
   */
  public function testSortRecursive() {
    $test_cases = array(
      array(
        'input' => array('b' => 'aa', 'c' => array('c2' => 'aa', 'c1' => 'aa'), 'a' => 'aa'),
        'expected' => array('a' => 'aa', 'b' => 'aa', 'c' => array('c1' => 'aa', 'c2' => 'aa')),
        'callback' => 'ksort',
      ),
    );
    foreach ($test_cases as $index => $test_case) {
      $output = $test_case['input'];
      redirect_sort_recursive($output, $test_case['callback']);
      $this->assertIdentical($output, $test_case['expected']);
    }
  }

  /**
   * Test redirect_parse_url().
   */
  public function testParseURL() {
    //$test_cases = array(
    //  array(
    //    'input' => array('b' => 'aa', 'c' => array('c2' => 'aa', 'c1' => 'aa'), 'a' => 'aa'),
    //    'expected' => array('a' => 'aa', 'b' => 'aa', 'c' => array('c1' => 'aa', 'c2' => 'aa')),
    //  ),
    //);
    //foreach ($test_cases as $index => $test_case) {
    //  $output = redirect_parse_url($test_case['input']);
    //  $this->assertIdentical($output, $test_case['expected']);
    //}
  }
}
