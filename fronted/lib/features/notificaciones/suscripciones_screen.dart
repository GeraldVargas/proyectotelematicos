import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/network/gateway_client.dart';
import '../../core/servicios.dart';
import '../../screens/home_screen.dart' show AppSettings, kPrimary, kAqua;
import '../mapa/ubicacion_service.dart';
import 'notificaciones_service.dart';

class SuscripcionesScreen extends StatefulWidget {
  final NotificacionesService? notificaciones;
  final UbicacionService? ubicacion;
  const SuscripcionesScreen({super.key, this.notificaciones, this.ubicacion});
  @override
  State<SuscripcionesScreen> createState() => _SuscripcionesScreenState();
}

class _SuscripcionesScreenState extends State<SuscripcionesScreen> {
  NotificacionesService get _api => widget.notificaciones ?? Servicios.notificaciones;
  List<Map<String, dynamic>> _avisos = [], _lineas = [];
  Set<String> _suscritas = {};
  final Set<String> _guardando = {};
  bool _cargandoAvisos = false, _cargandoLineas = true;
  String? _errorAvisos, _errorLineas;
  int _pagina = 1, _ultimaPagina = 1;
  Timer? _refresco;

  @override
  void initState() {
    super.initState();
    _cargarAvisos();
    _cargarLineas();
    _refresco = Timer.periodic(const Duration(seconds: 30), (_) {
      if (_pagina == 1) _cargarAvisos();
    });
  }

  @override
  void dispose() {
    _refresco?.cancel();
    super.dispose();
  }

  Future<void> _cargarAvisos({bool mas = false}) async {
    if (_cargandoAvisos) return;
    setState(() { _cargandoAvisos = true; _errorAvisos = null; });
    try {
      final pagina = mas ? _pagina + 1 : 1;
      final datos = await _api.avisos(pagina: pagina);
      if (!mounted) return;
      final nuevos = (datos['data'] as List).map((a) => Map<String, dynamic>.from(a as Map)).toList();
      setState(() {
        _avisos = mas ? [..._avisos, ...nuevos] : nuevos;
        _pagina = pagina;
        _ultimaPagina = (datos['last_page'] as num?)?.toInt() ?? 1;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _errorAvisos = e.mensaje);
    } catch (_) {
      if (mounted) setState(() => _errorAvisos = 'No pudimos cargar tus avisos. Intenta de nuevo.');
    } finally {
      if (mounted) setState(() => _cargandoAvisos = false);
    }
  }

  Future<void> _cargarLineas() async {
    setState(() { _cargandoLineas = true; _errorLineas = null; });
    try {
      final rutas = await (widget.ubicacion ?? Servicios.ubicacion).rutas();
      final suscritas = await _api.lineasSuscritas();
      final lineas = <String, Map<String, dynamic>>{};
      for (final f in rutas['features'] as List) {
        final p = Map<String, dynamic>.from(f['properties'] as Map);
        lineas['${p['linea']}'] = p;
      }
      if (mounted) setState(() { _lineas = lineas.values.toList(); _suscritas = suscritas; });
    } catch (_) {
      if (mounted) setState(() => _errorLineas = 'No pudimos cargar tus preferencias.');
    } finally {
      if (mounted) setState(() => _cargandoLineas = false);
    }
  }

  Future<void> _cambiar(String linea, bool activar) async {
    setState(() => _guardando.add(linea));
    try {
      if (activar) { await _api.suscribir(linea); }
      else { await _api.desuscribir(linea); }
      if (!mounted) return;
      setState(() => activar ? _suscritas.add(linea) : _suscritas.remove(linea));
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(activar ? 'Recibirás los próximos cambios de la línea $linea.' : 'Avisos de la línea $linea desactivados.'),
      ));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.mensaje)));
    } finally {
      if (mounted) setState(() => _guardando.remove(linea));
    }
  }

  String _fecha(dynamic valor) {
    final fecha = DateTime.tryParse('$valor')?.toLocal();
    if (fecha == null) return '';
    String dos(int n) => n.toString().padLeft(2, '0');
    return '${dos(fecha.day)}/${dos(fecha.month)}/${fecha.year} · ${dos(fecha.hour)}:${dos(fecha.minute)}';
  }

  Widget _mensaje(IconData icono, String titulo, String texto) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 16),
    child: Column(children: [Icon(icono, size: 42, color: kAqua),
      const SizedBox(height: 16), Text(titulo, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700), textAlign: TextAlign.center),
      const SizedBox(height: 8), Text(texto, textAlign: TextAlign.center)]),
  );

  Widget _error(String texto, VoidCallback reintentar) => Column(children: [
    Text(texto, textAlign: TextAlign.center),
    TextButton.icon(onPressed: reintentar, icon: const Icon(Icons.refresh), label: const Text('Reintentar')),
  ]);

  Widget _bandeja() => RefreshIndicator(
    onRefresh: _cargarAvisos,
    child: ListView(padding: const EdgeInsets.all(20), physics: const AlwaysScrollableScrollPhysics(), children: [
      const Text('Cambios en tus rutas', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
      const SizedBox(height: 6),
      const Text('Aquí aparecen los avisos cuando el administrador modifica una línea que sigues.'),
      const SizedBox(height: 20),
      if (_cargandoAvisos) const LinearProgressIndicator(),
      if (_errorAvisos != null) _error(_errorAvisos!, () => _cargarAvisos()),
      if (!_cargandoAvisos && _errorAvisos == null && _avisos.isEmpty)
        _mensaje(Icons.notifications_none_rounded, 'Todavía no tienes avisos',
          'Activa tus líneas en «Mis líneas». Los próximos cambios aparecerán aquí.'),
      for (final aviso in _avisos) Card(
        margin: const EdgeInsets.only(bottom: 12),
        child: Padding(padding: const EdgeInsets.all(18), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Icon(Icons.alt_route_rounded, color: kAqua), const SizedBox(width: 12),
            Expanded(child: Text('${aviso['titulo'] ?? 'Cambio de ruta'}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700))),
          ]),
          const SizedBox(height: 12), Text('${aviso['mensaje'] ?? ''}'),
          const SizedBox(height: 12), Text(_fecha(aviso['created_at']), style: const TextStyle(fontSize: 12)),
        ])),
      ),
      if (_pagina < _ultimaPagina) OutlinedButton(onPressed: _cargandoAvisos ? null : () => _cargarAvisos(mas: true), child: const Text('Ver avisos anteriores')),
    ]),
  );

  Widget _preferencias() => ListView(padding: const EdgeInsets.all(20), children: [
    const Text('Elige qué líneas seguir', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
    const SizedBox(height: 6), const Text('Activa una línea para recibir sus próximos avisos. Puedes desactivarla cuando quieras.'),
    const SizedBox(height: 16),
    Text('${_suscritas.length} ${_suscritas.length == 1 ? 'línea activada' : 'líneas activadas'}', style: const TextStyle(fontWeight: FontWeight.w700)),
    const SizedBox(height: 12),
    if (_cargandoLineas) const LinearProgressIndicator(),
    if (_errorLineas != null) _error(_errorLineas!, _cargarLineas),
    if (!_cargandoLineas && _errorLineas == null && _lineas.isEmpty)
      _mensaje(Icons.directions_bus_outlined, 'No hay líneas disponibles', 'Vuelve a consultar más tarde.'),
    for (final linea in _lineas) Card(child: SwitchListTile(
      secondary: const Icon(Icons.directions_bus_rounded, color: kAqua),
      title: Text('Línea ${linea['linea']}', style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Text('${linea['sindicato'] ?? linea['nombre'] ?? ''}'),
      value: _suscritas.contains('${linea['linea']}'),
      onChanged: _guardando.contains('${linea['linea']}') ? null : (v) => _cambiar('${linea['linea']}', v),
    )),
  ]);

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<bool>(
    valueListenable: AppSettings.darkMode,
    builder: (context, oscuro, _) {
      final scheme = ColorScheme.fromSeed(seedColor: kPrimary,
        brightness: oscuro ? Brightness.dark : Brightness.light,
        primary: oscuro ? kAqua : kPrimary, secondary: kAqua);
      return Theme(data: ThemeData(useMaterial3: true, colorScheme: scheme,
        scaffoldBackgroundColor: oscuro ? const Color(0xFF071E29) : const Color(0xFFF3F8F8),
        cardTheme: CardThemeData(elevation: 0, color: oscuro ? const Color(0xFF102F3B) : Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
      ), child: DefaultTabController(length: 2, child: Scaffold(
        appBar: AppBar(backgroundColor: kPrimary, foregroundColor: Colors.white,
          title: const Text('Notificaciones'),
          actions: [IconButton(tooltip: 'Actualizar avisos', onPressed: _cargandoAvisos ? null : () => _cargarAvisos(), icon: const Icon(Icons.refresh))],
          bottom: const TabBar(labelColor: Colors.white, unselectedLabelColor: Colors.white70,
            indicatorColor: kAqua, tabs: [Tab(text: 'Avisos', icon: Icon(Icons.notifications_outlined)), Tab(text: 'Mis líneas', icon: Icon(Icons.tune))]),
        ),
        body: TabBarView(children: [_bandeja(), _preferencias()]),
      )));
    },
  );
}
