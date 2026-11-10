// @ts-check
const eslint = require('@eslint/js');
const { defineConfig } = require('eslint/config');
const tseslint = require('typescript-eslint');
const angular = require('angular-eslint');

module.exports = defineConfig([
  {
    files: ['**/*.ts'],
    extends: [
      eslint.configs.recommended,
      tseslint.configs.strict,
      tseslint.configs.stylistic,
      angular.configs.tsRecommended,
    ],
    processor: angular.processInlineTemplates,
    rules: {
      '@typescript-eslint/no-extraneous-class': ['error', { allowWithDecorator: true }],
      // Guidelines « Angular » : règles de sécurité vérifiées en CI
      'no-restricted-syntax': [
        'error',
        {
          selector: 'MemberExpression[property.name=/^bypassSecurityTrust/]',
          message: 'bypassSecurityTrust* désactive la protection XSS d’Angular : exception revue obligatoire (eslint-disable + justification).',
        },
        {
          selector: 'MemberExpression[property.name=/^(innerHTML|outerHTML)$/]',
          message: 'Pas de manipulation directe du DOM : utiliser le binding Angular ou Renderer2.',
        },
        {
          selector: 'CallExpression[callee.object.name="document"][callee.property.name=/^(write|writeln)$/]',
          message: 'document.write est interdit.',
        },
      ],
      'no-restricted-properties': [
        'error',
        { object: 'Math', property: 'random', message: 'Pas d’aléatoire de sécurité avec Math.random : utiliser crypto.getRandomValues().' },
      ],
      'no-restricted-globals': [
        'error',
        { name: 'localStorage', message: 'Pas de jeton ni de donnée personnelle dans localStorage (guidelines Angular).' },
        { name: 'sessionStorage', message: 'Pas de jeton ni de donnée personnelle dans sessionStorage (guidelines Angular).' },
        { name: 'eval', message: 'eval est interdit.' },
      ],
      '@angular-eslint/directive-selector': [
        'error',
        {
          type: 'attribute',
          prefix: 'app',
          style: 'camelCase',
        },
      ],
      '@angular-eslint/component-selector': [
        'error',
        {
          type: 'element',
          prefix: 'app',
          style: 'kebab-case',
        },
      ],
    },
  },
  {
    files: ['**/*.html'],
    extends: [angular.configs.templateRecommended, angular.configs.templateAccessibility],
    rules: {},
  },
]);
