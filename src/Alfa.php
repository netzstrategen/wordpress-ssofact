<?php

/**
 * @file
 * Contains \Netzstrategen\Ssofact\Alfa.
 */

namespace Netzstrategen\Ssofact;

/**
 * Alfamedia VM integration functions.
 */
class Alfa {

  const ACCESS_WEB = 'OABO';

  public static function getProductMatrix() {
    $filepath = ABSPATH . '../ftp/alfa/gp/subscriptions.xml';
    $output_filepath = ABSPATH . '../ftp/alfa/gp/subcriptions.csv';
    $matrix = [];
    $all_products = [];
    $xml = simplexml_load_file($filepath);
    foreach ($xml as $bundle) {
      $accessType = (string) $bundle['accessType'];
      $matrix[$accessType]['accessType'] = $accessType;
      $matrix[$accessType]['bundle'] = (string) $bundle['bundle'];
      foreach ($bundle as $product) {
        $product_code = $product['kurztitel'] . ':' . $product['ausg_kuerzel'];
        $product_code .= ':' . $product->bezug_zusatz['schluessel_ivw'];
        $all_products[$product_code] = '';
        $matrix[$accessType]['products'][$product_code] = $product_code;
      }
      $matrix[$accessType]['count'] = count($matrix[$accessType]['products']);
    }
    // Sort all bundles by the count of products contained, so that purchases
    // will match the most feature-rich products first.
    uasort($matrix, function ($a, $b) {
      if ($a['count'] == $b['count']) {
        return 0;
      }
      return $a['count'] < $b['count'] ? 1 : -1;
    });

    // // Fill up all bundles with all possible product codes.
    // foreach ($matrix as $accessType => $bundle) {
    //   $matrix[$accessType]['checksum'] = crc32(implode('', $bundle['products']));
    //
    //   // CSV matrix export.
    //   $line = $matrix[$accessType];
    //   $line['bundle'] = '"' . $line['bundle'] . '"';
    //   $line['products'] += $all_products;
    //   ksort($line['products']);
    //   $line += $line['products'];
    //   $line['products'] = $matrix[$accessType]['checksum'];
    //   unlink($output_filepath);
    //   file_put_contents($output_filepath, implode(',', $line) . "\n", FILE_APPEND);
    // }
    return $matrix;
  }

  /**
   * Returns whether the given alfa accessType grants access to premium online articles.
   *
   * @param string $access_type
   *   The alfa accessType to check.
   *
   * @return bool
   */
  public static function isAccessTypeWeb(string $access_type) {
    $matrix = static::getProductMatrix();
    if (isset($matrix[$access_type])) {
      $matches = preg_grep('@\b' . static::ACCESS_WEB . '\b@', $matrix[$access_type]['products']);
      return !empty($matches);
    }
    return FALSE;
  }

  public static function getPurchases(array $alfa_purchases = NULL, $raw = FALSE) {
    if (!isset($alfa_purchases)) {
      $alfa_purchases = get_user_meta(get_current_user_ID(), 'alfa_purchases', TRUE);
    }
    if (empty($alfa_purchases)) {
      $alfa_purchases = ['purchases' => []];
    }
    $purchases = [];
    $today = date_i18n('Ymd');
    foreach ($alfa_purchases['purchases'] as $key => $purchase) {
      $purchase = &$purchase['purchase'];
      $expiration = $purchase['toDay'];
      if ($expiration < $today) {
        unset($alfa_purchases['purchases'][$key]);
        continue;
      }
      if ($raw) {
        $purchases[] = $purchase;
      }
      else {
        $purchases[$purchase['object']][$purchase['edition']][$purchase['ivw']] = $purchase['object'] . ':' . $purchase['edition'] . ':' . $purchase['ivw'];
      }
    }
    if (!$raw) {
      ksort($purchases);
    }
    return $purchases;
  }

  public static function getPurchasesFlattened(array $alfa_purchases = NULL) {
    $purchases = Alfa::getPurchases($alfa_purchases);
    $flattened_purchases = [];
    foreach ($purchases as $object => $editions) {
      foreach ($editions as $edition => $ivw_keys) {
        foreach ($ivw_keys as $ivw => $unused) {
          $product_code = $object . ':' . $edition . ':' . $ivw;
          $flattened_purchases[$product_code] = $product_code;
        }
      }
    }
    ksort($flattened_purchases);
    return $flattened_purchases;
  }

  public static function renderPurchases(array $purchases) {
    $object_labels = [
      'HST' => 'Täglich gedruckte Zeitung',
      'EST' => 'E-Paper im Web',
      'iST' => 'E-Paper als iStimme App',
      'OABO' => 'Stimme.de Premium',
      'mST' => 'mStimme mobil',
      'BES' => 'BES?',
      'TVS' => 'TVS?',
    ];
    $edition_labels = [
      'EH' => 'Heilbronn Stadtausgabe',
      'EHZK' => 'Hohenloher Zeitung Künzelsau',
      'EHZO' => 'Hohenloher Zeitung Öhringen',
      'EKS' => 'Kraichgau Stimme',
      'EN' => 'Heilbronner Stimme Nordausgabe',
      'EO' => 'Heilbronner Stimme Ostausgabe',
      'EW' => 'Heilbronner Stimme Westausgabe',
    ];
    $rows = [];
    foreach ($purchases as $key => &$purchase) {
      unset($purchase['type'], $purchase['ivw'], $purchase['ident'], $purchase['accessCount']);
      $purchase['date_start'] = preg_replace('@(\d{4})(\d{2})(\d{2})@', '$3.$2.$1', $purchase['fromDay']);
      $purchase['date_end'] = preg_replace('@(\d{4})(\d{2})(\d{2})@', '$3.$2.$1', $purchase['toDay']);
      $purchase['label'] = $object_labels[$purchase['object']];
      if (!empty($purchase['edition']) && isset($edition_labels[$purchase['edition']])) {
        $purchase['label'] .= ' - ' . $edition_labels[$purchase['edition']];
      }
    }
    ?>
<table>
  <thead>
    <tr>
      <th>Bezug</th>
      <th>von</th>
      <th>bis</th>
    </tr>
  </thead>
  <tbody>
    <?php
    foreach ($purchases as $purchase) {
      ?>
    <tr>
      <td><?= $purchase['label'] ?></td>
      <td><?= $purchase['date_start'] ?></td>
      <td><?= $purchase['date_end'] ?></td>
    </tr>
      <?php
    }
    ?>
  </tbody>
</table>
    <?php
  }

  public static function mapPurchases(array $purchases) {
    $matrix = static::getProductMatrix();
    $matches = [];

    $expected = $_GET['accessType'] ?? 'print';
    // echo "<pre>\n"; var_dump($matrix[$expected]); echo "</pre>";
    echo '<strong>Expected bundle:</strong> ' . $matrix[$expected]['bundle'] . ' (<code>' . $matrix[$expected]['accessType'] . '</code>)<br>';
    echo '<small>[append <code>?accessType=' . $expected . '</code> to the URL to change the expected product]</small><br>';
    echo '<strong>Expected products:</strong><br><ul>';
    foreach ($matrix[$expected]['products'] as $value) {
      echo '<li><code>' . $value . '</code></li>';
    }
    echo '</ul>';
    // echo "<pre>\n"; var_dump($purchases); echo "</pre>";
    echo '<strong>Actual products:</strong><br><ul>';
    foreach ($purchases as $value) {
      echo '<li><code>' . $value . '</code></li>';
    }
    echo '</ul>';
    echo '<strong>Matches in subscriptions.xml:</strong><br>';
    foreach ($matrix as $accessType => $bundle) {
      // echo "<pre>\n"; var_dump($accessType); echo "</pre>";
      // foreach ($purchases as $purchase) {
        if (($matched_products = array_intersect($bundle['products'], $purchases)) && $matched_products == $bundle['products']) {
          echo "\xE2\x9C\x85";
          // echo "<pre>\n"; var_dump($matched_products, '==', $bundle['products']); echo "</pre>";
          $matches[$accessType] = $bundle;
          $matches[$accessType]['products'] = $matched_products;
          $purchases = array_diff($purchases, $matched_products);
          // echo "<pre>\n"; var_dump($purchases); echo "</pre>";
        }
        else {
          echo "\xE2\x9D\x8C";
        }
      // }
      echo " $accessType<br>";
    }
    // echo "<pre>\n"; var_dump($matches); echo "</pre>";
    // exit;
  }

}
