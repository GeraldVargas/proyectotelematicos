import 'package:flutter/material.dart';

import '../../core/network/gateway_client.dart';
import '../../core/servicios.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _cargando = false;
  bool _verPassword = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _ingresar() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _cargando = true;
      _error = null;
    });
    try {
      await Servicios.auth.login(_email.text.trim(), _password.text);
      if (mounted) Navigator.pushReplacementNamed(context, '/mapa');
    } on ApiException catch (e) {
      setState(() => _error = e.mensaje);
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tema = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: Form(
                key: _form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Image.asset('assets/images/logo_appp.png', height: 96),
                    const SizedBox(height: 16),
                    Text('Iniciar sesión', style: tema.textTheme.headlineSmall, textAlign: TextAlign.center),
                    const SizedBox(height: 24),
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.email],
                      decoration: const InputDecoration(labelText: 'Correo', border: OutlineInputBorder()),
                      validator: (v) => v == null || !v.contains('@') ? 'Ingresa un correo válido' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _password,
                      obscureText: !_verPassword,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: (_) => _ingresar(),
                      decoration: InputDecoration(
                        labelText: 'Contraseña',
                        border: const OutlineInputBorder(),
                        suffixIcon: IconButton(
                          icon: Icon(_verPassword ? Icons.visibility_off : Icons.visibility),
                          onPressed: () => setState(() => _verPassword = !_verPassword),
                        ),
                      ),
                      validator: (v) => v == null || v.isEmpty ? 'Ingresa tu contraseña' : null,
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(_error!, style: TextStyle(color: tema.colorScheme.error), textAlign: TextAlign.center),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _cargando ? null : _ingresar,
                      child: _cargando
                          ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Text('Ingresar'),
                    ),
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: () => Navigator.pushReplacementNamed(context, '/home'),
                      child: const Text('Continuar sin iniciar sesión'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
