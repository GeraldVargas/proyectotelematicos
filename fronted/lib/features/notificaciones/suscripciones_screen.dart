import 'package:flutter/material.dart';

import '../../core/network/gateway_client.dart';
import '../../core/servicios.dart';

/// Lista de líneas con un interruptor para recibir o no sus avisos.
class SuscripcionesScreen extends StatefulWidget {
  const SuscripcionesScreen({super.key});

  @override
  State<SuscripcionesScreen> createState() => _SuscripcionesScreenState();
}

class _Linea {
  final String numero;
  final String nombre;
  final String? sindicato;

  _Linea(this.numero, this.nombre, this.sindicato);
}

class _SuscripcionesScreenState extends State<SuscripcionesScreen> {
  List<_Linea> _lineas = [];
  Set<String> _suscritas = {};
  final Set<String> _guardando = {};
  bool _cargando = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    setState(() {
      _cargando = true;
      _error = null;
    });
    try {
      final resultados = await Future.wait([Servicios.ubicacion.rutas(), Servicios.notificaciones.lineasSuscritas()]);
      final rutas = resultados[0] as Map<String, dynamic>;
      final lineas = <String, _Linea>{};
      for (final f in (rutas['features'] as List).cast<Map>()) {
        final p = f['properties'] as Map;
        final numero = p['linea'] as String;
        lineas[numero] = _Linea(numero, '${p['nombre'] ?? ''}', p['sindicato'] as String?);
      }
      if (!mounted) return;
      setState(() {
        _lineas = lineas.values.toList();
        _suscritas = resultados[1] as Set<String>;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.mensaje);
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  Future<void> _cambiar(String linea, bool activar) async {
    setState(() => _guardando.add(linea));
    try {
      if (activar) {
        await Servicios.notificaciones.suscribir(linea);
      } else {
        await Servicios.notificaciones.desuscribir(linea);
      }
      if (mounted) setState(() => activar ? _suscritas.add(linea) : _suscritas.remove(linea));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.mensaje)));
    } finally {
      if (mounted) setState(() => _guardando.remove(linea));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Avisos por línea')),
      body: _cuerpo(),
    );
  }

  Widget _cuerpo() {
    if (_cargando) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: _cargar, child: const Text('Reintentar')),
          ],
        ),
      );
    }
    return ListView.builder(
      itemCount: _lineas.length,
      itemBuilder: (_, i) {
        final linea = _lineas[i];
        return SwitchListTile(
          title: Text('Línea ${linea.numero}'),
          subtitle: Text(linea.sindicato ?? linea.nombre),
          value: _suscritas.contains(linea.numero),
          onChanged: _guardando.contains(linea.numero) ? null : (v) => _cambiar(linea.numero, v),
        );
      },
    );
  }
}
