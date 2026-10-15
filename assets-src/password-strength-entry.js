import { ZxcvbnFactory } from '@zxcvbn-ts/core';
import adjacencyGraphs from '@zxcvbn-ts/language-common/dist/adjacencyGraphs.json.mjs';
import translations from '@zxcvbn-ts/language-en/dist/translations.mjs';

// Keep the optional dictionary deliberately small for shared-host/mobile performance.
// The server's independent trivial-password policy is the authority, not this meter.
const evaluator = new ZxcvbnFactory({
  translations,
  graphs: adjacencyGraphs,
  dictionary: {
    'common-passwords': [
      '123456', 'password', '12345678', 'qwerty', '123456789', '12345', '1234',
      '111111', '1234567', 'dragon', '123123', 'baseball', 'football', 'monkey',
      'letmein', 'abc123', 'welcome', 'admin', 'passw0rd', 'password1',
      'password123', 'qwerty123', 'bulbula', 'bulbulaet'
    ]
  }
});
window.BulbulaPasswordStrength = (password, userInputs = []) => evaluator.check(password, userInputs);
