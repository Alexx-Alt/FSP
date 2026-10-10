import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';

const target = '.env';
const source = '.env.example';
let text = existsSync(target) ? readFileSync(target, 'utf8') : readFileSync(source, 'utf8');

const values = {
  APP_KEY: 'base64:' + randomBytes(32).toString('base64'),
  DB_PASSWORD: randomBytes(24).toString('hex'),
  MYSQL_ROOT_PASSWORD: randomBytes(24).toString('hex'),
  PRIVATE_DB_PASSWORD: randomBytes(24).toString('hex'),
  PRIVATE_MYSQL_ROOT_PASSWORD: randomBytes(24).toString('hex'),
  PRIVATE_LOOKUP_KEY: randomBytes(32).toString('hex'),
};

for (const [name, value] of Object.entries(values)) {
  const pattern = new RegExp('^' + name + '=(.*)$', 'm');
  const current = text.match(pattern)?.[1]?.trim();

  if (current) continue;

  if (pattern.test(text)) {
    text = text.replace(pattern, name + '=' + value);
  } else {
    text += '\n' + name + '=' + value + '\n';
  }
}

writeFileSync(target, text);
console.log('Создан .env с индивидуальными ключами. Не публикуйте его.');
