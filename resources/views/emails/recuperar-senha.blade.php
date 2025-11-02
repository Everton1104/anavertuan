<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recuperação de Senha</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color: #f6f0e6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f6f0e6;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #f0b99a; margin: 40px auto; padding: 20px; border-radius: 8px;">
                    <tr>
                        <td align="center" style="padding-bottom: 20px;">
                            <!-- Logo -->
                            <img src="{{url("/")}}/storage/logo-sm-bege.png" alt="Ana Vertuan Nutricionista" style="max-width: 300px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px; color: #333333;">
                            <h2 style="color: #333333;">Recuperação de Senha</h2>
                            <p>Olá,</p>
                            <p>Recebemos uma solicitação para redefinir sua senha. Para continuar, clique no botão abaixo:</p>
                            <p style="text-align: center; margin: 30px 0;">
                                <a href="{{ $url ?? 'sem-url' }}" style="background-color: #333333; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">Redefinir Senha</a>
                            </p>
                            <p>Se você não solicitou essa alteração, pode ignorar este e-mail com segurança.</p>
                            <p>Atenciosamente,<br><strong>Ana Vertuan Nutricionista</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-top: 20px; font-size: 12px; color: #999999;">
                            © {{ date('Y') }} Ana Vertuan Nutricionista. Todos os direitos reservados.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>