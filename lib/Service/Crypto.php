<?php
/**
 * nextCloud - RoundCube mail plugin
 *
 * @author 2019 Leonardo R. Morelli github.com/LeonardoRM
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\RoundCube\Service;

use OCP\ILogger;
use OCP\IL10N;
use OCP\Security\ISecureRandom;

/**
 * This class provides utilities for encryption/decryption and key pairs.
 */
class Crypto
{
  use \OCA\RoundCube\Traits\LoggerTrait;

  /** @var \OCP\Security\ISecureRandom */
  private $secureRandom;

  public function __construct(
    ISecureRandom $secureRandom
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->secureRandom = $secureRandom;
    $this->logger = $logger;
    $this->l = $l10n;
  }

  /**
   * Create the private and public keys with php defaults.
   * @param string $passphrase The passphrase to unlock private key.
   * @return array('privateKey', 'publicKey', 'encrytped') or false
   *         where first two values are strings and last one indicates whether
   *         the private key is encrypted with a passphrase.
   */
  public function generateKeyPair($passphrase = null) {
    $res = \openssl_pkey_new();
    /* Extract the private key from $res to string $privKey */
    if (!\openssl_pkey_export($res, $privKey, $passphrase)) {
      return false;
    }
    /* Extract the public key from $res to $pubKey */
    $publicRes = \openssl_pkey_get_details($res); // it's a resource
    if ($publicRes === false) {
      return false;
    }
    $pubKey = $publicRes['key']; // string
    return array(
      'privateKey' => $privKey,
      'publicKey'  => $pubKey,
      'encrypted'  => ($passphrase !== null && $passphrase !== "")
    );
  }

  /**
   * @param string $encPrivKey Private key protected with passphrase.
   * @param string $passphrase Can be null for no passphrase.
   * @return decrypted private key or false on error
   */
  private function showPrivKey($encPrivKey, $passphrase = null) {
    return \openssl_pkey_get_private($encPrivKey, $passphrase);
  }

  /**
   * @param int $length Length of token.
   * @return token
   */
  public function generateToken($length = 32) {
    return $this->secureRandom->generate($length);
  }

  /**
   * @param string $plainText Data to be encrypted.
   * @param string $pubKey    Public key.
   * @return string Encrypted data on base64 format or false.
   */
  public function publicEncrypt($plainText, $pubKey) {
    $this->logDebug("Starting encryption.");
    if (\openssl_public_encrypt($plainText, $encryptedData, $pubKey) === false) {
      $this->logError("Error during encryption.");
      return false;
    }
    $b64crypted = \base64_encode($encryptedData);
    return $b64crypted;
  }

  /**
   * @param string $b64crypted Encrypted data on base64 format.
   * @param string $privKey    Private key (encrypted with passphrase).
   * @param string $passphrase
   * @return string $plainText data or false.
   */
  public static function privateDecrypt($b64crypted, $privKey, $passphrase = null) {
    \OCP\Util::writeLog('roundcube', __METHOD__ . ": Starting decryption.", \OCP\Util::DEBUG);
    $encryptedData = \base64_decode($b64crypted);
    $privateKey = self::showPrivKey($privKey, $passphrase);
    if (\openssl_private_decrypt($encryptedData, $plainText, $privateKey) === false) {
      \OCP\Util::writeLog('roundcube', __METHOD__ . ": Decryption finished with errors.", \OCP\Util::ERROR);
      return false;
    }
    return $plainText;
  }
}
