<?php
/**
 * @file
 * Contains Drupal\site_audit\Plugin\SiteAuditCheck\ViewsCacheOutput
 */

namespace SiteAudit\Check;

use SiteAudit\SiteAuditCheckBase;
use SiteAudit\Util\SiteAuditEnvironment;

/**
 * Provides the ViewsCacheOutput Check.
 */
class ViewsCacheOutput extends SiteAuditCheckBase {

  /**
   * {@inheritdoc}.
   */
  public function getId() {
    return 'views_cache_output';
  }

  /**
   * {@inheritdoc}.
   */
  public function getLabel() {
    return $this->t('Rendered output caching');
  }

  /**
   * {@inheritdoc}.
   */
  public function getDescription() {
    return $this->t("Check to see if raw rendered output is being cached.");
  }

  /**
   * {@inheritdoc}.
   */
  public function getReportId() {
    return 'views';
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultFail() {
    return $this->t('No View is caching rendered output!');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultInfo() {
    return $this->getResultWarn();
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultPass() {
    return $this->t('Every View is caching rendered output.');
  }

  /**
   * {@inheritdoc}.
   */
  public function getResultWarn() {
    return $this->t('The following Views are not caching rendered output: @views_without_output_caching', array(
      '@views_without_output_caching' => implode(', ', $this->registry->views_without_output_caching),
    ));
  }

  /**
   * {@inheritdoc}.
   */
  public function getAction() {
    if (!in_array($this->score, array(SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO, SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS))) {

      $steps = array(
        $this->t('Go to /admin/structure/views/'),
        $this->t('Edit the View in question'),
        $this->t('Select the Display'),
        $this->t('Click Advanced'),
        $this->t('Next to Caching, click to edit.'),
        $this->t('Caching: (something other than None)'),
      );

      $ret_val = $this->t('Rendered output should be cached for as long as possible (if the query changes, the output will be refreshed).');
      $ret_val .= $this->linebreak();
      $ret_val .= $this->simpleList($steps, 'ol');

      return $ret_val;
    }
  }

  /**
   * {@inheritdoc}.
   */
  public function calculateScore() {
    $this->registry->output_lifespan = array();
    if (empty($this->registry->views)) {
      $this->checkInvokeCalculateScore('views_count');
    }
    foreach ($this->registry->views as $view) {
      // Skip views used for administration purposes.
      if (in_array($view->get('tag'), array('admin', 'commerce'))) {
        continue;
      }
      foreach ($view->get('display') as $display_name => $display) {
        if (!isset($display['display_options']['enabled']) || $display['display_options']['enabled']) {
          // Default display OR overriding display.
          if (isset($display['display_options']['cache'])) {
            if ($display['display_options']['cache']['type'] == 'none' || ($display['display_options']['cache'] == '')) {
              if ($display_name == 'default') {
                $this->registry->output_lifespan[$view->get('id')]['default'] = 'none';
              }
              else {
                $this->registry->output_lifespan[$view->get('id')]['displays'][$display_name] = 'none';
              }
            }
            elseif ($display['display_options']['cache']['type'] == 'time') {
              if ($display['display_options']['cache']['options']['output_lifespan'] == 0) {
                $lifespan = $display['display_options']['cache']['options']['output_lifespan_custom'];
              }
              else {
                $lifespan = $display['display_options']['cache']['options']['output_lifespan'];
              }
              if ($lifespan < 1) {
                $lifespan = 'none';
              }
              if ($display_name == 'default') {
                $this->registry->output_lifespan[$view->get('id')]['default'] = $lifespan;
              }
              else {
                $this->registry->output_lifespan[$view->get('id')]['displays'][$display_name] = $lifespan;
              }
            }
            elseif ($display['display_options']['cache']['type'] == 'tag') {
              if ($display_name == 'default') {
                $this->registry->output_lifespan[$view->get('id')]['default'] = 'tag';
              }
              else {
                $this->registry->output_lifespan[$view->get('id')]['displays'][$display_name] = 'tag';
              }
            }
          }
          // Display is using default display's caching.
          else {
            $this->registry->output_lifespan[$view->get('id')]['displays'][$display_name] = 'default';
          }
        }
      }
    }

    $this->registry->views_without_output_caching = array();

    foreach ($this->registry->output_lifespan as $view_name => $view_data) {
      // Views with only master display.
      if (!isset($view_data['displays']) || (count($view_data['displays']) == 0)) {
        if ($view_data['default'] == 'none') {
          $this->registry->views_without_output_caching[] = $view_name;
        }
      }
      else {
        // If all the displays are default, consolidate.
        $all_default_displays = TRUE;
        foreach ($view_data['displays'] as $display_name => $lifespan) {
          if ($lifespan != 'default') {
            $all_default_displays = FALSE;
          }
        }
        if ($all_default_displays) {
          if ($view_data['default'] == 'none') {
            $this->registry->views_without_output_caching[] = $view_name;
          }
        }
        else {
          $uncached_view_string = $view_name;
          $uncached_view_displays = array();
          foreach ($view_data['displays'] as $display_name => $display_data) {
            if ($display_data == 'none' || ($display_data == 'default' && $view_data['default'] == 'none')) {
              $uncached_view_displays[] = $display_name;
            }
          }
          if (!empty($uncached_view_displays)) {
            $uncached_view_string .= ' (' . implode(', ', $uncached_view_displays) . ')';
            $this->registry->views_without_output_caching[] = $uncached_view_string;
          }
        }
      }
    }

    if (count($this->registry->views_without_output_caching) == 0) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_PASS;
    }
    if (SiteAuditEnvironment::isDev()) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_INFO;
    }
    if (count($this->registry->views_without_output_caching) == count($this->registry->views)) {
      return SiteAuditCheckBase::AUDIT_CHECK_SCORE_FAIL;
    }
    return SiteAuditCheckBase::AUDIT_CHECK_SCORE_WARN;
  }

}
