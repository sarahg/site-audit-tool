<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\ExtensionsUnrecommended
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;

/**
 * Provides the ExtensionsUnrecommended Check.
 */
class ExtensionsUnrecommended extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'extensions_unrecommended';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Not recommended');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Check for unrecommended modules.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'extensions';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail() {
    $ret_val = $this->t('The following unrecommended modules(s) currently exist in your codebase: @list', array(
      '@list' => implode(', ', array_keys($this->registry->extensions_unrec)),
    ));
    if ($this->registry->detail) {
      $data = $this->rowsToKeyValueList($this->registry->extensions_unrec);
      $ret_val .= $this->linebreak();
      $ret_val .= $this->simpleKeyValueList($this->t('Name'), $this->t('Reason'), $data);
    }
    return $ret_val;
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {}

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {
    return $this->t('No unrecommended extensions were detected; no action required.', array());
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    return $this->t('There are @extension_count extensions enabled; that\'s higher than the average.', array(
      '@extension_count' => $this->registry->extension_count,
    ));
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {
    if ($this->getScore() != SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS) {
      return $this->t('Disable and completely remove unrecommended modules from your codebase for increased performance, stability and security in the any environment.');
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    $this->registry->extensions_unrec = array();
    if (!isset($this->registry->extensions)) {
      $this->checkInvokeCalculateScore('extensions_count');
    }
    $extension_info = $this->registry->extensions;
    #uasort($extension_info, '_drush_pm_sort_extensions');
    $unrecommended_extensions = $this->getExtensions();

    foreach ($extension_info as $extension) {
      $row = array();

      $machine_name = $extension->getName();

      // Not in the list of known unrecommended modules.
      if (!array_key_exists($machine_name, $unrecommended_extensions)) {
        continue;
      }

      // Name.
      $row[] = $extension->label;
      // Reason.
      $row[] = $unrecommended_extensions[$machine_name];

      $this->registry->extensions_unrec[$machine_name] = $row;
    }

    if (!empty($this->registry->extensions_unrec)) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
    }
    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
  }

  /**
   * Get a list of unrecommended extension names and reasons.
   *
   * @return array
   *    Keyed by module machine name, value is explanation.
   */
  public function getExtensions() {
    $unrecommended_modules = [
      'bad_judgement' => $this->t('Joke module, framework for anarchy.'),
      'php' => $this->t('Executable code should never be stored in the database.'),
    ];
    if ($this->registry->vendor == 'pantheon') {
      // Unsupported or redundant.
      $pantheon_unrecommended_modules = [
        'memcache' => dt('Pantheon does not provide memcache; instead, redis is provided as a service to all customers; see http://helpdesk.getpantheon.com/customer/portal/articles/401317'),
        'memcache_storage' => dt('Pantheon does not provide memcache; instead, redis is provided as a service to all customers; see http://helpdesk.getpantheon.com/customer/portal/articles/401317'),
      ];
      $unrecommended_modules = array_merge($unrecommended_modules, $pantheon_unrecommended_modules);
    }
    return $unrecommended_modules;
  }
}
