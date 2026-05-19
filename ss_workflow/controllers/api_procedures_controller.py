# -*- coding: utf-8 -*-
from collections import OrderedDict
from odoo import http, fields
from odoo.exceptions import ValidationError, UserError
from psycopg2.errors import UniqueViolation

from odoo.osv import expression
from odoo.addons.portal.controllers.portal import CustomerPortal, pager as portal_pager
from odoo.http import request
from werkzeug.utils import redirect
from datetime import datetime, timedelta
from pytz import timezone
import json
import base64
import math

class api_procedures_controller(http.Controller):

    
    @http.route('/jt_api/procedures/index', type='http', auth='none', methods=['GET'], csrf=False)
    def procedures_index(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner = user.partner
            
            page               = kw.get('page')
            search             = kw.get('search')
            
            order_by           = kw.get('order_by')
            order_ty           = kw.get('order_ty')
            condition_key      = kw.get('condition_key')
            condition_criteria = kw.get('condition_criteria')
            condition_value    = kw.get('condition_value')
            
            
            if not page:
                page = 1
            else:
                page = int(page)
            
            if page < 1:
                page = 1
            
            page_size = 25
            start = (page - 1) * page_size
            end = start + page_size
                
            # order
            if not order_by or order_by not in ['id', 'name', 'create_date']:
                order_by = "id"
            if not order_ty or order_ty not in ['ASC', 'DESC']:
                order_ty = "DESC"
                
            order = str(order_by) + ' ' + str(order_ty)
                
            payload = [('users_can_view', 'in', [user_partner.id]), ('deleted', '=', False)]
            
            if condition_key and condition_criteria and condition_value:
                if condition_key in ['state', 'template_id', 'create_date'] and condition_criteria in ['=', '!=', '<', '<=', '>', '>=']:
                    if condition_key in ['state', 'create_date']:
                        payload.append((
                            condition_key,
                            condition_criteria,
                            condition_value
                        ))
                    else:
                        payload.append((
                            condition_key,
                            condition_criteria,
                            int(condition_value)
                        ))
            
            if search and search != '':
                payload.append("|")
                payload.append("|")
                payload.append((
                        "id",
                        "ilike",
                        search
                    ))
                payload.append((
                        "name",
                        "ilike",
                        search
                    ))
                payload.append((
                        "description",
                        "ilike",
                        search
                    ))
                
            tasks       = request.env['ssw.procedures'].sudo().search(payload,
                                                                            limit=page_size, 
                                                                            offset=start, 
                                                                            order=order)
            tasks_count = request.env['ssw.procedures'].sudo().search_count(payload)
            
            
            results = []
            
            for task in tasks:
                template = task.template_id
                created_by = task.created_by
                
                item = {
                    "id": task.id,
                    "title": task.name,
                    "description": task.description,
                    "state": task.state,
                    "template": {
                        "id": template.id,
                        "name": template.name    
                    },
                    "created_by": {
                        "id": created_by.id,
                        "name": created_by.name    
                    },
                    "created_at": task.create_date
                }
                
            
                results.append(item)
                
            last_page = math.ceil(tasks_count / page_size)
            
            response = {
                'status': 'success',
                'data': results,
                'current_page': page,
                'last_page': last_page
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
     
    
    @http.route('/jt_api/tasks/index', type='http', auth='none', methods=['GET'], csrf=False)
    def tasks_index(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner = user.partner
            
            page               = kw.get('page')
            search             = kw.get('search')
            
            order_by           = kw.get('order_by')
            order_ty           = kw.get('order_ty')
            condition_key      = kw.get('condition_key')
            condition_criteria = kw.get('condition_criteria')
            condition_value    = kw.get('condition_value')
            
            
            if not page:
                page = 1
            else:
                page = int(page)
            
            if page < 1:
                page = 1
            
            page_size = 25
            start = (page - 1) * page_size
            end = start + page_size
                
            # order
            if not order_by or order_by not in ['id', 'name', 'create_date']:
                order_by = "id"
            if not order_ty or order_ty not in ['ASC', 'DESC']:
                order_ty = "DESC"
                
            order = str(order_by) + ' ' + str(order_ty)
                
            payload = [('users_can_view', 'in', [user_partner.id]), ('deleted', '=', False), ('state', '!=', 'draft')]
            
            if condition_key and condition_criteria and condition_value:
                if condition_key in ['state', 'assigned_to_dep', 'assigned_to_user', 'template_id', 'create_date'] and condition_criteria in ['=', '!=', '<', '<=', '>', '>=']:
                    if condition_key in ['state', 'create_date']:
                        payload.append((
                            condition_key,
                            condition_criteria,
                            condition_value
                        ))
                    else:
                        payload.append((
                            condition_key,
                            condition_criteria,
                            int(condition_value)
                        ))
            
            if search and search != '':
                payload.append("|")
                payload.append("|")
                payload.append((
                        "id",
                        "ilike",
                        search
                    ))
                payload.append((
                        "name",
                        "ilike",
                        search
                    ))
                payload.append((
                        "description",
                        "ilike",
                        search
                    ))
                
            tasks       = request.env['ssw.proc.tasks'].sudo().search(payload,
                                                                            limit=page_size, 
                                                                            offset=start, 
                                                                            order=order)
            tasks_count = request.env['ssw.proc.tasks'].sudo().search_count(payload)
            
            
            results = []
            
            for task in tasks:
                template = task.task_id
                created_by = task.procedure_id.created_by
                assigned_to_user = task.assigned_to_user
                assigned_to_user_details = False
                if assigned_to_user:
                    assigned_to_user_details = {
                        "id":   assigned_to_user.id,
                        "name": assigned_to_user.name
                    }
                    
                
                procedure   = task.procedure_id
                assigned_to_dep = task.assigned_to_dep
                item = {
                    "id": task.id,
                    "title": task.name,
                    "description": task.description,
                    "state": task.state,
                    "template": {
                        "id": template.id,
                        "name": template.name    
                    },
                    "procedure_id": {
                        "id": proc.id,
                        "name": proc.name    
                    },
                    "created_by": {
                        "id": created_by.id,
                        "name": created_by.name    
                    },
                    "assigned_to_user": assigned_to_user_details,
                    "assigned_to_dep": {
                        "id": assigned_to_dep.id,
                        "name": assigned_to_dep.name    
                    },
                    "created_at": task.create_date
                }
                
            
                results.append(item)
                
            last_page = math.ceil(tasks_count / page_size)
            
            response = {
                'status': 'success',
                'data': results,
                'current_page': page,
                'last_page': last_page
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
     
    @http.route('/jt_api/procedures/view', type='http', auth='none', methods=['GET'], csrf=False)
    def procedures_view(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner = user.partner
            
            procedure_id = kw.get('procedure_id')
            
            payload = [('id', '=', procedure_id), ('users_can_view', 'in', [user_partner.id]), ('deleted', '=', False)]
            procedure = request.env['ssw.procedures'].sudo().search(payload, limit=1)
            
            if not procedure:
                return self.response_json({'status': 'error', 'code':38, 'message': 'Task not found'})

            template   = proc.template_id
            created_by = proc.created_by
            
            tasks_fields = []
            for task in proc.tasks:
                assigned_to_user = task.assigned_to_user
                assigned_to_dep = task.assigned_to_dep
            
                assigned_to_user_details = False
                if assigned_to_user:
                    assigned_to_user_details = {
                        "id":   assigned_to_user.id,
                        "name": assigned_to_user.name
                    }
                    
                assigned_to_dep_details = False
                if assigned_to_dep:
                    assigned_to_dep_details = {
                        "id":   assigned_to_dep.id,
                        "name": assigned_to_dep.name
                    }
                    
                tasks_fields.append({
                    "id": task.id,
                    "name": task.name,
                    "description": task.description,
                    "state": task.state,
                    "assigned_to_user": assigned_to_user_details,
                    "assigned_to_dep": assigned_to_dep_details
                })
                
            results = {
                "id": proc.id,
                "title": proc.name,
                "description": proc.description,
                "state": proc.state,
                "template": {
                    "id": template.id,
                    "name": template.name    
                },
                "created_by": {
                    "id": created_by.id,
                    "name": created_by.name    
                },
                "tasks": tasks_fields,
                "created_at": proc.create_date
            }
            
            response = {
                'status': 'success',
                'data': results
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
     

    @http.route('/jt_api/tasks/view', type='http', auth='none', methods=['GET'], csrf=False)
    def tasks_view(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner = user.partner
            
            task_id = kw.get('task_id')
            
            payload = [('id', '=', task_id), ('users_can_view', 'in', [user_partner.id]), ('deleted', '=', False)]
            task = request.env['ssw.proc.tasks'].sudo().search(payload, limit=1)
            
            if not task:
                return self.response_json({'status': 'error', 'code':38, 'message': 'Task not found'})

            template   = task.task_id
            procedure   = task.procedure_id
            created_by = task.procedure_id.created_by
            assigned_to_user = task.assigned_to_user
            
            assigned_to_user_details = False
            if assigned_to_user:
                assigned_to_user_details = {
                    "id":   assigned_to_user.id,
                    "name": assigned_to_user.name
                }
            assigned_to_dep = task.assigned_to_dep
            
            assigned_to_dep_details = False
            if assigned_to_dep:
                assigned_to_dep_details = {
                    "id":   assigned_to_dep.id,
                    "name": assigned_to_dep.name
                }
                    
            inputs_items = []
            other_tasks_inputs_items = []
            inputs_records = request.env['ssw.proc.tasks.inputs'].sudo().search([('task_id', '=', task.id), ('deleted', '=', False)])
            other_tasks_inputs = task.other_tasks_inputs
            for input in inputs_records:
                input_image = input.value_image  # Use the appropriate image field (e.g., image_1920)
                if input_image:
                    # Convert binary image to base64
                    input_image_base64 = "data:image/png;base64," + input_image.decode()
                else:
                    input_image_base64 = None  # Fallback if no image is available
                    
                options = False
                if input.type == 'select':
                    options_records = request.env['ssw.proc.tasks.inputs.subs'].sudo().search([('input_id', '=', input.id), ('deleted', '=', False)])
                    options = []
                    for options_record in options_records:
                        options.append({
                            "id":    options_record.id,
                            "label": options_record.name
                        })
                inputs_items.append({
                    "id": input.id,
                    "label": input.name,
                    "is_required": input.is_required,
                    "type": input.type,
                    "result_value": input.result_value,
                    "value_image": input_image_base64,
                    "select_options": options
                })
                
            for input in other_tasks_inputs:
                input_image = input.value_image  # Use the appropriate image field (e.g., image_1920)
                if input_image:
                    # Convert binary image to base64
                    input_image_base64 = "data:image/png;base64," + input_image.decode()
                else:
                    input_image_base64 = None  # Fallback if no image is available
                    
                options = False
                if input.type == 'select':
                    options_records = request.env['ssw.proc.tasks.inputs.subs'].sudo().search([('input_id', '=', input.id), ('deleted', '=', False)])
                    options = []
                    for options_record in options_records:
                        options.append({
                            "id":    options_record.id,
                            "label": options_record.name
                        })
                other_tasks_inputs_items.append({
                    "id": input.id,
                    "label": input.name,
                    "task": {
                        "id": input.task_id.id,
                        "title": input.task_id.name
                    },
                    "type": input.type,
                    "result_value": input.result_value,
                    "value_image": input_image_base64,
                    "select_options": options
                })
            results = {
                "id":                    task.id,
                "title":                 task.name,
                "description":           task.description,
                "state":                 task.state,
                "procedure": {
                    "id":   proc.id,
                    "name": proc.name    
                },
                "template": {
                    "id":   template.id,
                    "name": template.name    
                },
                "created_by": {
                    "id":   created_by.id,
                    "name": created_by.name    
                },
                "assigned_to_user":       assigned_to_user_details,
                "assigned_to_dep":        assigned_to_dep_details,
                "inputs":                 inputs_items,
                "other_tasks_inputs":     other_tasks_inputs_items,
                "created_at":             task.create_date
            }
            
            response = {
                'status': 'success',
                'data': results
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
     
    @http.route('/jt_api/tasks/assign', type='http', auth='none', methods=['GET'], csrf=False)
    def tasks_toggle_assign(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner = user.partner
            
            task_id   = kw.get('task_id')
            assign    = kw.get('assign')
            
            payload = [('id', '=', task_id), ('users_can_view', 'in', [user_partner.id]), ('deleted', '=', False), ('state', '!=', 'draft')]
            task = request.env['ssw.proc.tasks'].sudo().search(payload, limit=1)
            
            if not task:
                return self.response_json({'status': 'error', 'code':38, 'message': 'Task not found'})

            if task.assigned_to_user and task.assigned_to_user.id == user_partner.id:
                task.assigned_to_user = False
                result = True
            elif not task.assigned_to_user:
                task.assigned_to_user = user_partner.id
                result = True
            else:
                result = False
            
            response = {
                'status': 'success',
                'result': result
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
     
    @http.route('/jt_api/tasks/state', type='http', auth='none', methods=['GET'], csrf=False)
    def tasks_toggle_state(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner = user.partner
            
            task_id = kw.get('task_id')
            state    = kw.get('state')
            if state == "draft":
                return self.response_json({'status': 'error', 'code': 2, 'message': 'Unable to process the request'})
            
            payload = [('id', '=', task_id), ('users_can_view', 'in', [user_partner.id]), ('deleted', '=', False), ('state', '!=', 'draft')]
            task = request.env['ssw.proc.tasks'].sudo().search(payload, limit=1)
            
            if not task:
                return self.response_json({'status': 'error', 'code':38, 'message': 'Task not found'})
            
            task.state = state
            
            response = {
                'status': 'success',
                'result': True
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
     
    @http.route('/jt_api/procedures/templates', type='http', auth='none', methods=['GET'], csrf=False)
    def procedures_templates(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner = user.partner
            
            task_user = request.env['ssw.users'].sudo().search([('partner_id', '=', user_partner.id)], limit=1)
            if task_user: 
                groups_can_see = task_user.groups_can_see.ids
                groups_domain = ('default_group', 'in', groups_can_see)
            else:
                groups_domain = ('default_group', '=', False)
                
            payload = [('enabled', '=', True), ('deleted', '=', False), groups_domain, ('departments_can_create', 'in', [task_user.default_department.id])]

            templates = request.env['ssw.proc.templates'].sudo().search(payload)
            
            results = []
            for template in templates:
                
                image_icon_base64 = None  # Fallback if no image is available
                    
                results.append({
                    "id": template.id,
                    "icon": image_icon_base64,
                    "title": template.name,
                    "description": template.description
                })
            
            response = {
                'status': 'success',
                'data': results
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
    
    @http.route('/jt_api/procedures/departments', type='http', auth='none', methods=['GET'], csrf=False)
    def procedures_departments(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner = user.partner
            
            task_user = request.env['ssw.users'].sudo().search([('partner_id', '=', user_partner.id)], limit=1)
            if not task_user: 
                return self.response_json({'status': 'error', 'code':37, 'message': 'User not whitelisted for this action'})
            
            
            results = []
            for department in task_user.departments_can_assign:
                
                results.append({
                    "id": department.id,
                    "title": department.name
                })
            
            response = {
                'status': 'success',
                'data': results
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
    
    @http.route('/jt_api/tasks/submit_procedure', type='http', auth='none', methods=['POST'], csrf=False)
    def tasks_submit(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner     = user.partner
            
            name        = kw.get('title')
            description = kw.get('description')
            template_id = kw.get('template_id')
            
            task_user = request.env['ssw.users'].sudo().search([('partner_id', '=', user_partner.id)], limit=1)
            if task_user: 
                groups_can_see = task_user.groups_can_see.ids
                groups_domain = ('default_group', 'in', groups_can_see)
            else:
                groups_domain = ('default_group', '=', False)
                
            payload = [('id', '=', template_id), ('enabled', '=', True), ('deleted', '=', False), groups_domain, ('departments_can_create', 'in', [task_user.default_department.id])]

            template = request.env['ssw.proc.templates'].sudo().search(payload, limit=1)
            
            if not template:
                return self.response_json({'status': 'error', 'code': 4, 'message': 'bad_inputs'})
            
            procedure = request.env['ssw.procedures'].sudo().create({
                "name": name,
                "description": description,
                "template_id": template.id,
                "created_by": user_partner.id
            })
            
            response = {
                'status': 'success',
                'data': proc.id
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/tasks/input_submit', type='http', auth='none', methods=['POST'], csrf=False)
    def tasks_input_submit(self, **kw):
        try:
            user = self.get_user(request)
        
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            if user.check_throttle(route_name="tasks_comments_submit", hits=10, minute=1, ip_address=http.request.httprequest.remote_addr):
                return self.response_json({'status': 'error', 'code': 9, 'message': 'Too Many Requests'})
            
            user_partner = user.partner
            
            task_id       = int(kw.get('task_id'))
            body_inputs   = kw.get('body_inputs')
            
            body_inputs = json.loads(str(body_inputs))
                
            user_id = user.user_id
            if not user_id:
                user_id = 1
            
            payload = [('id', '=', task_id), ('users_can_view', 'in', [user_partner.id]), ('deleted', '=', False), ('state', '=', 'pending')]
            task = request.env['ssw.proc.tasks'].sudo().search(payload, limit=1)
            
            if not task:
                return self.response_json({'status': 'error', 'code':38, 'message': 'Task not found'})
            
            if task.state in ['rejected', 'completed']:
                return self.response_json({'status': 'error', 'code':39, 'message': 'State not suitable for tasks'})
            
            for input_record_item in body_inputs:
                input_id        = int(input_record_item['input_id'])
                value           = input_record_item['value']
                inputs_record = request.env['ssw.proc.tasks.inputs'].with_user(1).sudo().search([('id', '=', input_id), ('task_id', '=', task.id), ('deleted', '=', False)])
                
                if not inputs_record:
                    return self.response_json({'status': 'error', 'code': 4, 'message': 'bad_inputs'})
                
                if inputs_record.type == "char":
                    inputs_record.value_char = value
                    
                if inputs_record.type == "int":
                    inputs_record.value_int = value
                    
                if inputs_record.type == "date":
                    inputs_record.value_date = value
                    
                if inputs_record.type == "datetime":
                    inputs_record.value_datetime = value
                    
                if inputs_record.type == "boolean":
                    inputs_record.value_boolean = value
                    
                if inputs_record.type == "image":
                    image = request.httprequest.files.get('image_' + str(input_id))
                    if image:
                        inputs_record.value_image = base64.b64encode(image.read())
                    
                if inputs_record.type == "select":
                    inputs_record.value_select = int(value)

            
            return self.response_json({'status': 'success', 'result': True})
            
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'message': str(e)
            })
        except (UniqueViolation, UserError) as e:
            return self.response_json({
                'status': 'error',
                'message': str(e)
            })
        except Exception as e:
            return self.response_json({'status': 'error', 'code': 4, 'message': 'bad_inputs'})
    
    # comments
    @http.route('/jt_api/tasks/comments', type='http', auth='none', methods=['GET'], csrf=False)
    def tasks_comments(self, **kw):
        try:
            user = self.get_user(request)
            
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            user_partner = user.partner
            
            page = kw.get('page')
            task_id = int(kw.get('task_id'))
            
            payload = [('id', '=', task_id), ('users_can_view', 'in', [user_partner.id]), ('deleted', '=', False)]
            task = request.env['ssw.proc.tasks'].sudo().search(payload, limit=1)
            
            if not task:
                return self.response_json({'status': 'error', 'code':38, 'message': 'Task not found'})
            
            if not page:
                page = 1
            else:
                page = int(page)
                
            if page < 1:
                page = 1
            
            page_size = 25
            start = (page - 1) * page_size
            end = start + page_size
  
            comments = request.env['mail.message'].sudo().search([('res_id', '=', task.id), ('model', '=', 'ssw.proc.tasks'), "|", ('body', '!=', ""), "|", ('attachment_ids', '!=', False), ('tracking_value_ids', '!=', False)],
                                                                limit=page_size, 
                                                                offset=start, 
                                                                order='id desc')
            comments_count = request.env['mail.message'].sudo().search_count([('res_id', '=', task.id), ('model', '=', 'ssw.proc.tasks'), "|", ('body', '!=', ""), "|", ('attachment_ids', '!=', False), ('tracking_value_ids', '!=', False)])
            last_page = math.ceil(comments_count / page_size)
            results = []
            
            for comment in comments:
                create_user = comment.author_id
                
                attachments = []
                for attachment in comment.attachment_ids:
                    attachment.public = True
                    file_id = attachment.id
                    checksum = attachment.checksum
                    file_size = attachment.file_size
                    display_name = str(attachment.display_name).replace(" ", "_")
                    description = attachment.description
                    mimetype = attachment.mimetype
                    type = attachment.type
                    link = f"/web/image/{file_id}?filename={display_name}&unique={checksum}"
                    attachments.append({
                        "display_name": display_name,
                        "file_size": file_size,
                        "description": description,
                        "mimetype": mimetype,
                        "type": type,
                        "link": link
                    })
                
                # Prepare the image
                create_user_image = create_user.image_128  # Use the appropriate image field (e.g., image_1920)
                if create_user_image:
                    # Convert binary image to base64
                    create_user_image_base64 = "data:image/png;base64," + create_user_image.decode()
                else:
                    create_user_image_base64 = None  # Fallback if no image is available
                    
                tracking = comment.tracking_value_ids
                tracking_result = []
                if tracking:
                    for track in tracking:
                        old_value = False
                        new_value = False
                        if track.field_id.ttype == "char" or track.field_id.ttype == "selection":
                            old_value = track.old_value_char
                            new_value = track.new_value_char
                        elif track.field_id.ttype == "datetime":
                            old_value = track.old_value_datetime
                            new_value = track.new_value_datetime
                        elif track.field_id.ttype == "float":
                            old_value = track.old_value_float
                            new_value = track.new_value_float
                        elif track.field_id.ttype == "integer":
                            old_value = track.old_value_integer
                            new_value = track.new_value_integer
                        elif track.field_id.ttype == "text":
                            old_value = track.old_value_text
                            new_value = track.old_value_text
                        else:
                            old_value = track.old_value_char
                            new_value = track.new_value_char
                            
                        tracking_result.append({
                            "id": track.id,
                            "field": track.field_id.display_name,
                            "type": track.field_id.ttype,
                            "old_value": old_value,
                            "new_value": new_value
                        })
                    
                results.append({
                    "id": comment.id,
                    "user": {
                        "id": create_user.id,
                        "name": create_user.name,
                        "profile_image": create_user_image_base64
                        },
                    "type": comment.message_type,
                    "body": comment.body,
                    "reply_to": comment.reply_to,
                    "tracking": tracking_result,
                    "attachments": attachments,
                    "pinned_at": comment.pinned_at,
                    "created_at": comment.create_date
                })
            # Return user profile
            response = {
                'status': 'success',
                'data': results,
                'current_page': page,
                'last_page': last_page
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code': 4, 'message': 'bad_inputs'})
    
    @http.route('/jt_api/tasks/comments_submit', type='http', auth='none', methods=['POST'], csrf=False)
    def tasks_comments_submit(self, **kw):
        try:
            user = self.get_user(request)
        
            if not user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            
            if user.check_throttle(route_name="tasks_comments_submit", hits=10, minute=1, ip_address=http.request.httprequest.remote_addr):
                return self.response_json({'status': 'error', 'code': 9, 'message': 'Too Many Requests'})
            
            user_partner = user.partner
            
            task_id = int(kw.get('task_id'))
            body = kw.get('body')
            
            user_id = user.user_id
            if not user_id:
                user_id = 1
            
            payload = [('id', '=', task_id), ('users_can_view', 'in', [user_partner.id]), ('deleted', '=', False)]
            task = request.env['ssw.proc.tasks'].with_user(1).sudo().search(payload, limit=1)
            
            if not task:
                return self.response_json({'status': 'error', 'code':38, 'message': 'Task not found'})
            
            if task.state != 'pending':
                return self.response_json({'status': 'error', 'code':39, 'message': 'State not suitable for tasks'})
            
            task.message_post(
                body=body,
                message_type="comment",
                author_id=user_partner.id
            )
            
            return self.response_json({'status': 'success', 'result': True})
            
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'message': str(e)
            })
        except (UniqueViolation, UserError) as e:
            return self.response_json({
                'status': 'error',
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code': 4, 'message': 'bad_inputs'})
    

    def response_json(self, responseData):
        response = http.Response(
                json.dumps(responseData, default=str),
                status=200,
                mimetype='application/json'
            )
        response.headers['Access-Control-Allow-Origin'] = '*'
        response.headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS, PUT, DELETE'
        response.headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, Origin, X-Requested-With, Accept'
        return response
    
    def get_user(self, request):
        if 'Authorization' not in request.httprequest.headers:
            return False
                
        token = request.httprequest.headers['Authorization']
        return request.env['jtapi.users'].sudo().auth_user(token, ['tasks'])
   
