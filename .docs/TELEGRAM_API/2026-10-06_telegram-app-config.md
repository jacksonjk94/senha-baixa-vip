# Configuração — Telegram App (my.telegram.org)

**Data:** 06/10/2026 · **Projeto:** AgendaFácil
**Origem:** my.telegram.org → API development tools

---

## 📋 Dados da Aplicação

| Campo | Valor |
|---|---|
| **App api_id** | `39784027` |
| **App api_hash** | `c70308042703f1bbf6e297338b906124` |
| **App title** | `AgendaFacil26` |
| **Nome curto (short name)** | `agendafacil26` |

---

## 🔌 Servidores MTProto Disponíveis

| Ambiente | Endereço | DC |
|---|---|---|
| **Test configuration** | `149.154.167.40:443` | DC 2 |
| **Production configuration** | `149.154.167.50:443` | DC 2 |

---

## 🔑 Chaves Públicas RSA

### Test Configuration

```
-----BEGIN RSA PUBLIC KEY-----
MIIBCgKCAQEAyMehDY18ar+scCRZ53rJztktKqIgv/vBfqCJ2tZt57QMGgx36
XIRyy7Y7m6d8/S0Fa/1mAzTEolDK9hoqF4VWb6XeqgFOAUIJdDpHRcKkR20
Fwvp1l0U1PLtKtZ9uW2E3b+1xNvJj3GWBDJPQEFBE+vfmH0JP503wr5lNSI9po
Wg/J25sIMEhYPheORfP/eXaqhISP6G+q2IeTakMXpowZ4LzXQ5opkBvYEQgmvRq
7D1aHwfYmIE6epFaYR8Q9yqvvhYtMte3ITnuS3I71xGdqdKcSwHnd6FuduG0A4p
cC0j4iDcU2cCTHh8gFTIhp/Y8/SpD0hvN9QIDAQAB
-----END RSA PUBLIC KEY-----
```

### Production Configuration

```
-----BEGIN RSA PUBLIC KEY-----
MIIBCgKCAQEAz6LszBcCIlGzyr992NzE0eIeY+BSaOW622Aa9Bd4ZHLI+TuF41
o4g5nKaHwrB/B1b9XUfg0Z9/21R6Zr9kM7HjuiIcCzFvDtr+L6GQjaH9hPR8B
20062cEC5hKH7SD29K33vmvHiLonc621dQumKNSQjF50XYFw42h2P1KXU6gyp
2y/+aEJz+uVgLLQBRa1dE5DZ2IGrY12Mk5gpYc397ApY438FsJoHIg2JtgMv5h7
hVY9t6h/BY9N0p210g3AoXSL2g/2I3IWUhebgAdGVW1Vf1kuDQoE2zR7EdpqtQD
9Cs5+bf0jN3mcyvK5tB0Mk39z6bNZ7XyrPBw8s
-----END RSA PUBLIC KEY-----
```

---

## ⚠️ Credenciais Adicionais (seção vazia na imagem — preencher quando disponível)

| Credencial | Status |
|---|---|
| **FCM credentials** | (a preencher) |
| **WNS credentials** | (a preencher) |

---

## 🔒 Recomendações de Segurança

- ⚠️ `api_id` e `api_hash` são credenciais sensíveis — manter no `.env`, **nunca expor publicamente** nem commitar no repositório.
- ✅ Adicionar ao `.gitignore`: `.env`
- ✅ Botão **"Save changes"** no my.telegram.org para confirmar as configurações.

### Exemplo de `.env`:

```env
TELEGRAM_API_ID=39784027
TELEGRAM_API_HASH=c70308042703f1bbf6e297338b906124
TELEGRAM_APP_TITLE=AgendaFacil26
TELEGRAM_APP_SHORT_NAME=agendafacil26
```

---

*Arquivo gerado em 06/10/2026 — Curitiba/PR*
