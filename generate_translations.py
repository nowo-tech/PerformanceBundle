#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script para generar archivos de traducción para 30 idiomas
"""

import yaml
import os

# Estructura base de traducciones en inglés
base_structure = {
    'dashboard': {
        'title': 'Performance Metrics Dashboard',
        'subtitle': 'Monitor and analyze route performance metrics',
        'advanced_statistics': 'Advanced Statistics',
        'export_csv': 'Export CSV',
        'export_json': 'Export JSON',
        'clear_all_records': 'Clear All Records',
        'back_to_dashboard': 'Back to Dashboard',
        'no_routes_found': 'No routes found for the selected filters.'
    },
    'statistics': {
        'total_routes': 'Total Routes',
        'total_queries': 'Total Queries',
        'avg_request_time': 'Avg Request Time',
        'max_request_time': 'Max Request Time',
        'avg_query_time': 'Avg Query Time',
        'max_query_time': 'Max Query Time',
        'max_queries': 'Max Queries',
        'not_available': 'N/A'
    },
    'routes_table': {
        'title': 'Routes',
        'route_name': 'Route Name',
        'environment': 'Environment',
        'request_time': 'Request Time',
        'query_time': 'Query Time',
        'total_queries': 'Total Queries',
        'memory_usage': 'Memory Usage',
        'access_count': 'Access Count',
        'last_accessed_at': 'Last Accessed At',
        'updated_at': 'Updated At',
        'review_status': 'Review Status',
        'actions': 'Actions',
        'reviewed': 'Reviewed',
        'not_reviewed': 'Not Reviewed',
        'queries_improved': 'Queries: ✓ Improved',
        'queries_not_improved': 'Queries: ✗ Not Improved',
        'time_improved': 'Time: ✓ Improved',
        'time_not_improved': 'Time: ✗ Not Improved',
        'delete_record': 'Delete record',
        'mark_as_reviewed': 'Mark as reviewed',
        'delete': 'Delete',
        'review': 'Review',
        'cancel': 'Cancel',
        'confirm_delete': 'Are you sure you want to delete this record?'
    },
    'filters': {
        'title': 'Filters',
        'show_advanced': 'Show Advanced Filters',
        'hide_advanced': 'Hide Advanced Filters',
        'advanced_filters': 'Advanced Filters',
        'environment': 'Environment',
        'route_name': 'Route Name',
        'sort_by': 'Sort By',
        'order': 'Order',
        'limit': 'Limit',
        'min_request_time': 'Min Request Time (s)',
        'max_request_time': 'Max Request Time (s)',
        'min_query_count': 'Min Query Count',
        'max_query_count': 'Max Query Count',
        'date_from': 'Date From',
        'date_to': 'Date To',
        'apply_filters': 'Apply Filters',
        'reset': 'Reset',
        'placeholder_route': 'Filter by route name...',
        'placeholder_time': '0.0000',
        'placeholder_count': '0'
    },
    'review': {
        'modal_title': 'Mark as Reviewed',
        'queries_improved': 'Queries Improved?',
        'time_improved': 'Time Improved?',
        'not_specified': 'Not specified',
        'yes': 'Yes',
        'no': 'No',
        'mark_as_reviewed': 'Mark as Reviewed'
    },
    'confirmations': {
        'clear_all_records': 'Are you sure you want to delete all performance records? This action cannot be undone.',
        'delete_record': 'Are you sure you want to delete this record?'
    },
    'flash': {
        'records_cleared': 'All records have been successfully deleted.',
        'record_deleted': 'The record has been successfully deleted.',
        'record_reviewed': 'The record has been successfully marked as reviewed.',
        'error': 'An error occurred while processing the request.'
    },
    'statistics_page': {
        'title': 'Advanced Performance Statistics',
        'subtitle': 'Detailed statistical analysis to identify optimization targets',
        'routes_needing_attention': 'Routes Needing Attention',
        'routes_above_p95': 'Routes above 95th percentile',
        'routes_with_outliers': 'Routes with outliers',
        'metric': 'Metric',
        'mean': 'Mean',
        'median': 'Median',
        'mode': 'Mode',
        'std_deviation': 'Standard Deviation',
        'min': 'Min',
        'max': 'Max',
        'range': 'Range',
        'p25': 'P25',
        'p50': 'P50',
        'p75': 'P75',
        'p90': 'P90',
        'p95': 'P95',
        'p99': 'P99',
        'outliers': 'Outliers',
        'histogram': 'Distribution Histogram',
        'request_time': 'Request Time',
        'query_time': 'Query Time',
        'query_count': 'Query Count',
        'memory_usage': 'Memory Usage',
        'access_count': 'Access Count'
    },
    'sort_options': {
        'request_time': 'Request Time',
        'query_time': 'Query Time',
        'queries': 'Queries',
        'access_count': 'Access Count',
        'route_name': 'Route Name'
    },
    'order_options': {
        'descending': 'Descending',
        'ascending': 'Ascending'
    }
}

# Traducciones para cada idioma
translations = {
    'it': {  # Italiano
        'dashboard': {'title': 'Dashboard Metriche di Prestazione', 'subtitle': 'Monitora e analizza le metriche di prestazione delle route', 'advanced_statistics': 'Statistiche Avanzate', 'export_csv': 'Esporta CSV', 'export_json': 'Esporta JSON', 'clear_all_records': 'Cancella Tutti i Record', 'back_to_dashboard': 'Torna al Dashboard', 'no_routes_found': 'Nessuna route trovata per i filtri selezionati.'},
        'statistics': {'total_routes': 'Route Totali', 'total_queries': 'Query Totali', 'avg_request_time': 'Tempo Medio Richiesta', 'max_request_time': 'Tempo Massimo Richiesta', 'avg_query_time': 'Tempo Medio Query', 'max_query_time': 'Tempo Massimo Query', 'max_queries': 'Query Massime', 'not_available': 'N/D'},
        'routes_table': {'title': 'Route', 'route_name': 'Nome Route', 'environment': 'Ambiente', 'request_time': 'Tempo Richiesta', 'query_time': 'Tempo Query', 'total_queries': 'Query Totali', 'memory_usage': 'Uso Memoria', 'access_count': 'Numero Accessi', 'last_accessed_at': 'Ultimo Accesso', 'updated_at': 'Aggiornato', 'review_status': 'Stato Revisione', 'actions': 'Azioni', 'reviewed': 'Revisionato', 'not_reviewed': 'Non Revisionato', 'queries_improved': 'Query: ✓ Migliorato', 'queries_not_improved': 'Query: ✗ Non Migliorato', 'time_improved': 'Tempo: ✓ Migliorato', 'time_not_improved': 'Tempo: ✗ Non Migliorato', 'delete_record': 'Elimina record', 'mark_as_reviewed': 'Segna come revisionato', 'delete': 'Elimina', 'review': 'Revisiona', 'cancel': 'Annulla', 'confirm_delete': 'Sei sicuro di voler eliminare questo record?'},
        'filters': {'title': 'Filtri', 'show_advanced': 'Mostra Filtri Avanzati', 'hide_advanced': 'Nascondi Filtri Avanzati', 'advanced_filters': 'Filtri Avanzati', 'environment': 'Ambiente', 'route_name': 'Nome Route', 'sort_by': 'Ordina Per', 'order': 'Ordine', 'limit': 'Limite', 'min_request_time': 'Tempo Min Richiesta (s)', 'max_request_time': 'Tempo Max Richiesta (s)', 'min_query_count': 'Numero Min Query', 'max_query_count': 'Numero Max Query', 'date_from': 'Data Da', 'date_to': 'Data A', 'apply_filters': 'Applica Filtri', 'reset': 'Reimposta', 'placeholder_route': 'Filtra per nome route...', 'placeholder_time': '0.0000', 'placeholder_count': '0'},
        'review': {'modal_title': 'Segna come Revisionato', 'queries_improved': 'Query Migliorate?', 'time_improved': 'Tempo Migliorato?', 'not_specified': 'Non specificato', 'yes': 'Sì', 'no': 'No', 'mark_as_reviewed': 'Segna come Revisionato'},
        'confirmations': {'clear_all_records': 'Sei sicuro di voler eliminare tutti i record di prestazione? Questa azione non può essere annullata.', 'delete_record': 'Sei sicuro di voler eliminare questo record?'},
        'flash': {'records_cleared': 'Tutti i record sono stati eliminati con successo.', 'record_deleted': 'Il record è stato eliminato con successo.', 'record_reviewed': 'Il record è stato segnato come revisionato con successo.', 'error': 'Si è verificato un errore durante l\'elaborazione della richiesta.'},
        'statistics_page': {'title': 'Statistiche Avanzate di Prestazione', 'subtitle': 'Analisi statistica dettagliata per identificare obiettivi di ottimizzazione', 'routes_needing_attention': 'Route che Richiedono Attenzione', 'routes_above_p95': 'Route sopra il 95° percentile', 'routes_with_outliers': 'Route con valori anomali', 'metric': 'Metrica', 'mean': 'Media', 'median': 'Mediana', 'mode': 'Moda', 'std_deviation': 'Deviazione Standard', 'min': 'Min', 'max': 'Max', 'range': 'Intervallo', 'p25': 'P25', 'p50': 'P50', 'p75': 'P75', 'p90': 'P90', 'p95': 'P95', 'p99': 'P99', 'outliers': 'Valori Anomali', 'histogram': 'Istogramma di Distribuzione', 'request_time': 'Tempo Richiesta', 'query_time': 'Tempo Query', 'query_count': 'Numero Query', 'memory_usage': 'Uso Memoria', 'access_count': 'Numero Accessi'},
        'sort_options': {'request_time': 'Tempo Richiesta', 'query_time': 'Tempo Query', 'queries': 'Query', 'access_count': 'Numero Accessi', 'route_name': 'Nome Route'},
        'order_options': {'descending': 'Decrescente', 'ascending': 'Crescente'}
    },
    'pt': {  # Português
        'dashboard': {'title': 'Painel de Métricas de Desempenho', 'subtitle': 'Monitorar e analisar métricas de desempenho de rotas', 'advanced_statistics': 'Estatísticas Avançadas', 'export_csv': 'Exportar CSV', 'export_json': 'Exportar JSON', 'clear_all_records': 'Limpar Todos os Registros', 'back_to_dashboard': 'Voltar ao Painel', 'no_routes_found': 'Nenhuma rota encontrada para os filtros selecionados.'},
        'statistics': {'total_routes': 'Total de Rotas', 'total_queries': 'Total de Consultas', 'avg_request_time': 'Tempo Médio de Solicitação', 'max_request_time': 'Tempo Máximo de Solicitação', 'avg_query_time': 'Tempo Médio de Consulta', 'max_query_time': 'Tempo Máximo de Consulta', 'max_queries': 'Máximo de Consultas', 'not_available': 'N/D'},
        'routes_table': {'title': 'Rotas', 'route_name': 'Nome da Rota', 'environment': 'Ambiente', 'request_time': 'Tempo de Solicitação', 'query_time': 'Tempo de Consulta', 'total_queries': 'Total de Consultas', 'memory_usage': 'Uso de Memória', 'access_count': 'Número de Acessos', 'last_accessed_at': 'Último Acesso', 'updated_at': 'Atualizado', 'review_status': 'Status de Revisão', 'actions': 'Ações', 'reviewed': 'Revisado', 'not_reviewed': 'Não Revisado', 'queries_improved': 'Consultas: ✓ Melhorado', 'queries_not_improved': 'Consultas: ✗ Não Melhorado', 'time_improved': 'Tempo: ✓ Melhorado', 'time_not_improved': 'Tempo: ✗ Não Melhorado', 'delete_record': 'Excluir registro', 'mark_as_reviewed': 'Marcar como revisado', 'delete': 'Excluir', 'review': 'Revisar', 'cancel': 'Cancelar', 'confirm_delete': 'Tem certeza de que deseja excluir este registro?'},
        'filters': {'title': 'Filtros', 'show_advanced': 'Mostrar Filtros Avançados', 'hide_advanced': 'Ocultar Filtros Avançados', 'advanced_filters': 'Filtros Avançados', 'environment': 'Ambiente', 'route_name': 'Nome da Rota', 'sort_by': 'Ordenar Por', 'order': 'Ordem', 'limit': 'Limite', 'min_request_time': 'Tempo Mín de Solicitação (s)', 'max_request_time': 'Tempo Máx de Solicitação (s)', 'min_query_count': 'Número Mín de Consultas', 'max_query_count': 'Número Máx de Consultas', 'date_from': 'Data De', 'date_to': 'Data Até', 'apply_filters': 'Aplicar Filtros', 'reset': 'Redefinir', 'placeholder_route': 'Filtrar por nome da rota...', 'placeholder_time': '0.0000', 'placeholder_count': '0'},
        'review': {'modal_title': 'Marcar como Revisado', 'queries_improved': 'Consultas Melhoradas?', 'time_improved': 'Tempo Melhorado?', 'not_specified': 'Não especificado', 'yes': 'Sim', 'no': 'Não', 'mark_as_reviewed': 'Marcar como Revisado'},
        'confirmations': {'clear_all_records': 'Tem certeza de que deseja excluir todos os registros de desempenho? Esta ação não pode ser desfeita.', 'delete_record': 'Tem certeza de que deseja excluir este registro?'},
        'flash': {'records_cleared': 'Todos os registros foram excluídos com sucesso.', 'record_deleted': 'O registro foi excluído com sucesso.', 'record_reviewed': 'O registro foi marcado como revisado com sucesso.', 'error': 'Ocorreu um erro ao processar a solicitação.'},
        'statistics_page': {'title': 'Estatísticas Avançadas de Desempenho', 'subtitle': 'Análise estatística detalhada para identificar alvos de otimização', 'routes_needing_attention': 'Rotas que Precisam de Atenção', 'routes_above_p95': 'Rotas acima do percentil 95', 'routes_with_outliers': 'Rotas com valores atípicos', 'metric': 'Métrica', 'mean': 'Média', 'median': 'Mediana', 'mode': 'Moda', 'std_deviation': 'Desvio Padrão', 'min': 'Min', 'max': 'Max', 'range': 'Intervalo', 'p25': 'P25', 'p50': 'P50', 'p75': 'P75', 'p90': 'P90', 'p95': 'P95', 'p99': 'P99', 'outliers': 'Valores Atípicos', 'histogram': 'Histograma de Distribuição', 'request_time': 'Tempo de Solicitação', 'query_time': 'Tempo de Consulta', 'query_count': 'Número de Consultas', 'memory_usage': 'Uso de Memória', 'access_count': 'Número de Acessos'},
        'sort_options': {'request_time': 'Tempo de Solicitação', 'query_time': 'Tempo de Consulta', 'queries': 'Consultas', 'access_count': 'Número de Acessos', 'route_name': 'Nome da Rota'},
        'order_options': {'descending': 'Decrescente', 'ascending': 'Crescente'}
    }
}

# Idiomas a crear (excluyendo en, es, fr, de que ya existen o se crearon)
languages = {
    'it': 'Italiano',
    'pt': 'Português',
    'nl': 'Nederlands',
    'pl': 'Polski',
    'ru': 'Русский',
    'tr': 'Türkçe',
    'el': 'Ελληνικά',
    'cs': 'Čeština',
    'ro': 'Română',
    'hu': 'Magyar',
    'sv': 'Svenska',
    'no': 'Norsk',
    'da': 'Dansk',
    'fi': 'Suomi',
    'hr': 'Hrvatski',
    'bg': 'Български',
    'zh_CN': '简体中文',
    'zh_TW': '繁體中文',
    'ja': '日本語',
    'ko': '한국어',
    'ar': 'العربية',
    'hi': 'हिन्दी',
    'id': 'Bahasa Indonesia',
    'th': 'ไทย',
    'vi': 'Tiếng Việt',
    'he': 'עברית'
}

def create_translation_file(lang_code, lang_name, translations_dict):
    """Crea un archivo de traducción para un idioma"""
    filename = f'src/Resources/translations/nowo_performance.{lang_code}.yaml'
    
    # Si ya existe, no lo sobrescribimos
    if os.path.exists(filename):
        print(f'⚠️  {filename} ya existe, omitiendo...')
        return
    
    # Usar traducciones personalizadas si existen, sino usar estructura base
    if lang_code in translations_dict:
        data = translations_dict[lang_code]
    else:
        # Para idiomas sin traducciones específicas, usar inglés como fallback
        data = base_structure
    
    with open(filename, 'w', encoding='utf-8') as f:
        f.write(f'# Translations for Nowo Performance Bundle ({lang_name})\n')
        f.write(f'# Domain: nowo_performance\n\n')
        yaml.dump(data, f, allow_unicode=True, default_flow_style=False, sort_keys=False)
    
    print(f'✅ Creado: {filename}')

if __name__ == '__main__':
    # Crear directorio si no existe
    os.makedirs('src/Resources/translations', exist_ok=True)
    
    # Crear archivos para todos los idiomas
    for lang_code, lang_name in languages.items():
        create_translation_file(lang_code, lang_name, translations)
    
    print(f'\n✅ Se han creado {len(languages)} archivos de traducción')
