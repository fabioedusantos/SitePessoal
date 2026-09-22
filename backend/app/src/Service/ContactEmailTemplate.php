<?php

declare(strict_types=1);

namespace App\Service;

/** Templates com tabelas e estilos inline, compatíveis com os principais clientes de e-mail. */
final class ContactEmailTemplate
{
    public function owner(string $name, string $email, string $subject, string $message): string
    {
        $name = $this->escape($name);
        $email = $this->escape($email);
        $subject = $this->escape($subject);
        $message = nl2br($this->escape($message));

        $content = '<p style="margin:0 0 20px;color:#495d73;font-size:15px;line-height:1.7">Uma nova mensagem chegou pelo formulário de contato do seu site profissional.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:0 0 22px">'
            . $this->detail('Nome', $name)
            . $this->detail('E-mail', $email)
            . $this->detail('Assunto', $subject)
            . '</table>'
            . '<p style="margin:0 0 9px;font-size:12px;font-weight:700;letter-spacing:1.2px;color:#51708b;text-transform:uppercase">Mensagem recebida</p>'
            . '<div style="padding:20px 22px;background:#f1f6fb;border-left:4px solid #1586de;border-radius:5px;color:#1b3147;font-size:15px;line-height:1.8;overflow-wrap:anywhere">' . $message . '</div>'
            . '<p style="margin:20px 0 0;color:#708296;font-size:13px;line-height:1.6">Para responder, utilize a função Responder do seu e-mail. O endereço do remetente já está configurado.</p>';

        return $this->layout('Novo contato pelo site', 'Você recebeu uma nova mensagem', $content, 'NOTIFICAÇÃO DE CONTATO');
    }

    public function confirmation(string $name): string
    {
        $name = $this->escape($name);
        $content = '<p style="margin:0 0 17px;color:#203b53;font-size:16px;line-height:1.75">Olá, <strong>' . $name . '</strong>!</p>'
            . '<p style="margin:0 0 17px;color:#495d73;font-size:15px;line-height:1.8">Obrigado por entrar em contato pelo meu site profissional. Sua mensagem foi recebida e encaminhada para mim.</p>'
            . '<p style="margin:0 0 26px;color:#495d73;font-size:15px;line-height:1.8">Assim que possível, vou analisar o contexto e responder diretamente ao endereço de e-mail informado.</p>'
            . '<div style="border-top:1px solid #e3ebf2;padding-top:21px;color:#173b5f;font-size:15px;line-height:1.65">Até breve,<br><strong>Fábio Santos</strong><br><span style="font-size:13px;color:#698099">Fábio Santos Desenvolvimentos</span></div>';

        return $this->layout('Mensagem recebida', 'Obrigado pelo seu contato', $content, 'CONFIRMAÇÃO DE CONTATO');
    }

    private function detail(string $label, string $value): string
    {
        return '<tr><td style="width:88px;padding:9px 8px 9px 0;border-bottom:1px solid #e8eef4;vertical-align:top;color:#718396;font-size:13px">' . $label . '</td>'
            . '<td style="padding:9px 0;border-bottom:1px solid #e8eef4;vertical-align:top;color:#1b334c;font-size:14px;font-weight:600;overflow-wrap:anywhere">' . $value . '</td></tr>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function layout(string $title, string $headline, string $content, string $eyebrow): string
    {
        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $this->escape($title) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#edf3f9;font-family:Arial,Helvetica,sans-serif;color:#18324a">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#edf3f9"><tr><td align="center" style="padding:40px 14px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;border-collapse:separate;border-spacing:0;background:#ffffff;border-radius:13px;overflow:hidden">'
            . '<tr><td style="background:#061a2c;padding:28px 34px;border-bottom:4px solid #1586de">'
            . '<span style="color:#ffffff;font-size:22px;font-weight:800;letter-spacing:-.7px">FB<span style="color:#51b2ff">Santos</span></span>'
            . '<div style="margin-top:5px;font-size:11px;letter-spacing:1.2px;color:#a9c2d8">FÁBIO SANTOS DESENVOLVIMENTOS</div></td></tr>'
            . '<tr><td style="padding:37px 34px 38px"><p style="margin:0 0 9px;font-size:11px;font-weight:700;letter-spacing:1.6px;color:#1686d9">' . $eyebrow . '</p>'
            . '<h1 style="margin:0 0 23px;font-size:27px;line-height:1.2;letter-spacing:-.7px;color:#102c46">' . $this->escape($headline) . '</h1>'
            . $content . '</td></tr>'
            . '<tr><td style="background:#f7f9fc;padding:24px 34px;border-top:1px solid #e4edf4">'
            . '<p style="margin:0 0 7px;color:#294d6d;font-weight:700;font-size:13px">FBSantos.com.br</p>'
            . '<p style="margin:0;color:#7b8a99;font-size:12px;line-height:1.7">Comunicação enviada automaticamente pelo formulário do site.<br>'
            . '<a href="https://fbsantos.com.br" style="color:#177bc2;text-decoration:none">Visitar site profissional</a></p>'
            . '</td></tr></table>'
            . '<p style="max-width:600px;margin:18px 0 0;color:#94a4b2;font-size:11px;line-height:1.6">© FBSantos. Esta mensagem é referente a um contato realizado em fbsantos.com.br.</p>'
            . '</td></tr></table></body></html>';
    }
}
