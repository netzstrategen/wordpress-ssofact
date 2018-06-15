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

  public static function getProductMatrix() {
    $filepath = ABSPATH . '../ftp/alfa/vm/subscriptions.xml';
    $output_filepath = ABSPATH . '../ftp/alfa/vm/subcriptions.csv';
    unlink($output_filepath);
    $matrix = [];
    $all_products = [];
    $xml = simplexml_load_file($filepath);
    foreach ($xml as $bundle) {
      $accessType = (string) $bundle['accessType'];
      $matrix[$accessType]['accessType'] = $accessType;
      $matrix[$accessType]['bundle'] = (string) $bundle['bundle'];
      foreach ($bundle as $product) {
        $product_code = $product['kurztitel'] . ':' . $product['ausg_kuerzel'];
        // $product_code .= ':' . $product->bezug_zusatz['schluessel_ivw'];
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

    // Fill up all bundles with all possible product codes.
    foreach ($matrix as $accessType => $bundle) {
      $matrix[$accessType]['checksum'] = crc32(implode('', $bundle['products']));

      // CSV matrix export.
      $line = $matrix[$accessType];
      $line['bundle'] = '"' . $line['bundle'] . '"';
      $line['products'] += $all_products;
      ksort($line['products']);
      $line += $line['products'];
      $line['products'] = $matrix[$accessType]['checksum'];
      file_put_contents($output_filepath, implode(',', $line) . "\n", FILE_APPEND);
    }
    return $matrix;
  }

  public static function getPurchases() {
    $alfa_purchases = file_get_contents(ABSPATH . 'purchases.json');
    $alfa_purchases = json_decode($alfa_purchases, TRUE);
    $purchases = [];
    $today = date('Ymd');
    foreach ($alfa_purchases['purchases'] as $key => $purchase) {
      $purchase = &$purchase['purchase'];
      $expiration = $purchase['toDay'];
      if ($expiration < $today) {
        unset($alfa_purchases['purchases'][$key]);
        continue;
      }
      $product_code = $purchase['object'] . ':' . $purchase['edition'];
      // $product_code .= ':' . $purchase['ivw'];
      $purchases[$product_code] = $product_code;
    }
    ksort($purchases);
    return $purchases;
  }

  public static function mapPurchases(array $purchases) {
    $matrix = static::getProductMatrix();
    $matches = [];
    echo "<pre>\n"; var_dump($purchases); echo "</pre>";
    foreach ($matrix as $accessType => $bundle) {
      echo "<pre>\n"; var_dump($accessType); echo "</pre>";
      // foreach ($purchases as $purchase) {
        if ($matched_products = array_intersect($bundle['products'], $purchases)) {
          if ($matched_products == $bundle['products']) {
            echo "<pre>\n"; var_dump($matched_products, '==', $bundle['products']); echo "</pre>";
            $matches[$accessType] = $bundle;
            $matches[$accessType]['products'] = $matched_products;
            $purchases = array_diff($purchases, $matched_products);
            echo "<pre>\n"; var_dump($purchases); echo "</pre>";
          }
        }
      // }
    }
    echo "<pre>\n"; var_dump($matches); echo "</pre>";
    exit;
  }

}
