(function () {
  var LIMIT = 48 * 1024 * 1024;
  var TEXT_PREFIX = 'datadock-enc:v1:';

  function bytesToBase64(bytes) {
    var parts = [];
    var chunk = 0x8000;
    for (var i = 0; i < bytes.length; i += chunk) {
      parts.push(String.fromCharCode.apply(null, bytes.subarray(i, i + chunk)));
    }
    return btoa(parts.join(''));
  }

  function base64ToBytes(value) {
    var binary = atob(value);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
  }

  async function deriveKey(passphrase, salt) {
    var material = await crypto.subtle.importKey(
      'raw',
      new TextEncoder().encode(passphrase),
      'PBKDF2',
      false,
      ['deriveKey']
    );
    return crypto.subtle.deriveKey(
      { name: 'PBKDF2', salt: salt, iterations: 100000, hash: 'SHA-256' },
      material,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );
  }

  async function encryptBytes(plain, passphrase) {
    if (!crypto.subtle) {
      throw new Error('This browser cannot encrypt here. Open the site over https, or on localhost.');
    }
    var salt = crypto.getRandomValues(new Uint8Array(16));
    var iv = crypto.getRandomValues(new Uint8Array(12));
    var key = await deriveKey(passphrase, salt);
    var cipher = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, key, plain));
    var out = new Uint8Array(4 + salt.length + iv.length + cipher.length);
    out.set([0x44, 0x44, 0x45, 0x31], 0);
    out.set(salt, 4);
    out.set(iv, 20);
    out.set(cipher, 32);
    return out;
  }

  async function decryptBytes(payload, passphrase) {
    if (payload.length < 33 || payload[0] !== 0x44 || payload[1] !== 0x44 || payload[2] !== 0x45 || payload[3] !== 0x31) {
      throw new Error('This file was not sealed by DataDock.');
    }
    var salt = payload.slice(4, 20);
    var iv = payload.slice(20, 32);
    var cipher = payload.slice(32);
    var key = await deriveKey(passphrase, salt);
    var plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: iv }, key, cipher);
    return new Uint8Array(plain);
  }

  async function encryptText(text, passphrase) {
    var sealed = await encryptBytes(new TextEncoder().encode(text), passphrase);
    return TEXT_PREFIX + bytesToBase64(sealed);
  }

  async function decryptText(stored, passphrase) {
    if (stored.indexOf(TEXT_PREFIX) !== 0) {
      throw new Error('This paste was not sealed by DataDock.');
    }
    var plain = await decryptBytes(base64ToBytes(stored.slice(TEXT_PREFIX.length)), passphrase);
    return new TextDecoder().decode(plain);
  }

  window.datadockEncrypt = {
    limit: LIMIT,
    encryptBytes: encryptBytes,
    decryptBytes: decryptBytes,
    encryptText: encryptText,
    decryptText: decryptText
  };
})();
