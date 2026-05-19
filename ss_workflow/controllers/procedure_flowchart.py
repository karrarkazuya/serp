# -*- coding: utf-8 -*-

import json

from odoo import http
from odoo.http import request


class ProcedureTemplateFlowchartController(http.Controller):

    @http.route('/ss_workflow/procedures/template/<int:template_id>/flowchart', auth='user', type='http')
    def procedure_template_flowchart(self, template_id, **kwargs):
        template = request.env['ssw.proc.templates'].browse(template_id)
        if not template.exists():
            return request.not_found()

        template.check_access_rights('read')
        template.check_access_rule('read')

        payload = template._get_flowchart_payload()
        return request.render('ss_workflow.procedure_template_flowchart_page', {
            'template_record': template,
            'flowchart_json': json.dumps(payload, ensure_ascii=False),
        })

    @http.route('/ss_workflow/procedures/template/<int:template_id>/flowchart/layout', auth='user', type='http', methods=['POST'], csrf=False)
    def save_procedure_template_flowchart_layout(self, template_id, **kwargs):
        template = request.env['ssw.proc.templates'].browse(template_id)
        if not template.exists():
            return request.not_found()

        template.check_access_rights('write')
        template.check_access_rule('write')

        try:
            payload = json.loads(request.httprequest.data or '{}')
        except ValueError:
            return self.response_json({'status': 'error', 'message': 'Invalid JSON'}, status=400)

        positions = payload.get('positions', [])
        if not isinstance(positions, list):
            return self.response_json({'status': 'error', 'message': 'Invalid positions'}, status=400)

        task_model = request.env['ssw.proc.templates.tasks']
        template_task_ids = set(template.tasks.filtered(lambda task: not task.deleted).ids)

        for position in positions:
            if not isinstance(position, dict):
                continue

            try:
                task_id = int(position.get('id'))
            except (TypeError, ValueError):
                continue

            if task_id not in template_task_ids:
                continue

            try:
                x = max(int(round(float(position.get('x', 0)))), 0)
                y = max(int(round(float(position.get('y', 0)))), 0)
            except (TypeError, ValueError):
                continue

            task = task_model.browse(task_id)
            task.write({
                'flowchart_position_saved': True,
                'flowchart_x': x,
                'flowchart_y': y,
            })

        return self.response_json({'status': 'success'})

    @http.route('/ss_workflow/procedures/template/<int:template_id>/flowchart/layout/reset', auth='user', type='http', methods=['POST'], csrf=False)
    def reset_procedure_template_flowchart_layout(self, template_id, **kwargs):
        template = request.env['ssw.proc.templates'].browse(template_id)
        if not template.exists():
            return request.not_found()

        template.check_access_rights('write')
        template.check_access_rule('write')

        template.tasks.filtered(lambda task: not task.deleted).write({
            'flowchart_position_saved': False,
            'flowchart_x': 0,
            'flowchart_y': 0,
        })

        return self.response_json({'status': 'success'})
        
    @http.route('/ssw_api/tickets_next_task', type='http', auth='none', methods=['GET'], csrf=False)
    def tickets_add_next_task_to_all(self, **kw):
        
        tasks = request.env['ssw.proc.tasks'].sudo().search([('deleted', '=', False)])
        
        for task in tasks:
            task.updateContext()
            
        tickets = request.env['ssw.tickets'].sudo().search([('deleted', '=', False)])
        
        for ticket in tickets:
            ticket.updateContext()
            
        return self.response_json({'status': 'error', 'code': 1, 'message': 'bad_inputs'})
    
    
    
    def response_json(self, responseData, status=200):
        response = http.Response(
                json.dumps(responseData, default=str),
                status=status,
                mimetype='application/json'
            )
        response.headers['Access-Control-Allow-Origin'] = '*'
        response.headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS, PUT, DELETE'
        response.headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, Origin, X-Requested-With, Accept'
        return response
