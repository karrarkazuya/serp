# -*- coding: utf-8 -*-
from collections import OrderedDict
import base64
from odoo import http, fields
from odoo.exceptions import ValidationError, UserError

from odoo.osv import expression
from odoo.addons.portal.controllers.portal import CustomerPortal, pager as portal_pager
from odoo.http import request
from werkzeug.utils import redirect
from datetime import datetime, timedelta
from pytz import timezone
import json
import math

class evaluation_controller(http.Controller):

    # evaluations
    @http.route('/jt_api/hr/evaluations/index/<int:employee_id>/page/<int:page>', type='http', auth='none', methods=['GET'], csrf=False)
    def evaluations_index(self, employee_id=0, page=1, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            if not page:
                page = 1
            else:
                page = int(page)
                
            if page < 1:
                page = 1
            
            page_size = 100
            start = (page - 1) * page_size
            end = start + page_size
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            if employee_id != employee.id and employee_id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 14,'message': 'You do not have permission for this employee'})
            
            employee = request.env['hr.employee'].sudo().browse([employee_id])
            
            payload = [( "employee_id", "=", employee.id), ( "parent_id", "=", employee.parent_id.id), ( "deleted", "=", False)]

            results = request.env['jtemployees.evaluation.groups'].sudo().search(payload, 
                                                                    limit=page_size, 
                                                                    offset=start, 
                                                                    order='id desc')
            
            records_count = request.env['jtemployees.evaluation.groups'].sudo().search_count(payload)
            
            last_page = math.ceil(records_count / page_size)
            
            final_resuls = []
            
            for item in results:
                values_data = request.env['jtemployees.evaluation.values'].sudo().search([('group_id', '=', item.id)])
                values = []
                for value in values_data:
                    values.append({
                        "id" : value.id,
                        "name": value.name,
                        "objective" : value.objective.name,
                        "note": value.note,
                        "employee_id": {
                            "id": value.employee_id.id,
                            "name": value.employee_id.name
                        },
                        "percentage": value.percentage
                    })
                final_resuls.append({
                    "id": item.id,
                    "created_at": item.create_date,
                    "values": values
                })
            
            response = {
                'status': 'success',
                'data': final_resuls,
                'current_page': page,
                'last_page': last_page
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/evaluations/create', type='http', auth='none', methods=['POST'], csrf=False)
    def create_evaluations(self):
        try:
            params         = request.get_json_data()
            employee_id    = params['employee_id']
            auth_user      = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            employee_ids = employee.child_ids.ids
            
            if employee_id and employee_id > 0 and employee_id in employee_ids:
                employee_ids = [employee_id]
            
            for sub_employee in employee_ids:
                payload = {
                    "employee_id": sub_employee,
                }
                request.env['jtemployees.evaluation.groups'].with_user(user).sudo().create(payload)
                
            return self.response_json({'status': 'success'})
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/evaluations/delete', type='http', auth='none', methods=['POST'], csrf=False)
    def delete_evaluations(self):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            params        = request.get_json_data()
            item_id       = params['item_id']
            
            employee   = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            evaluation_group = request.env['jtemployees.evaluation.groups'].sudo().search([('id', '=', item_id)], limit=1)
            
            if evaluation_group.employee_id.id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 14,'message': 'You do not have permission for this employee'})
            
            request.env['jtemployees.evaluation.values'].sudo().search([
                (
                    'group_id', '=', evaluation_group.id
                )
            ]).unlink()
            evaluation_group.unlink()
            
            return self.response_json({'status': 'success'})
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
    
    @http.route('/jt_api/hr/evaluations/edit', type='http', auth='none', methods=['POST'], csrf=False)
    def edit_evaluations(self):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            params        = request.get_json_data()
            item_id       = params['item_id']
            percentage    = params['percentage']
            note          = params['note']
            
            employee   = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            evaluation = request.env['jtemployees.evaluation.values'].sudo().search([('id', '=', item_id)], limit=1)
            
            if evaluation.employee_id.id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 14,'message': 'You do not have permission for this employee'})
            
            evaluation.note = note
            evaluation.percentage = percentage
            
            return self.response_json({'status': 'success', 'evaluation': evaluation.read(fields=["id", "name", "objective", "note", "employee_id", "percentage"])})
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
    
    # objectives
    @http.route('/jt_api/hr/evaluations/objectives/index/<int:employee_id>/page/<int:page>', type='http', auth='none', methods=['GET'], csrf=False)
    def objectives_index(self, employee_id=0, page=1, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            if not page:
                page = 1
            else:
                page = int(page)
                
            if page < 1:
                page = 1
            
            page_size = 100
            start = (page - 1) * page_size
            end = start + page_size
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            if employee_id != employee.id and employee_id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 14,'message': 'You do not have permission for this employee'})
            
            employee = request.env['hr.employee'].sudo().browse([employee_id])
            
            payload = [( "employee_id", "=", employee.id), ( "parent_id", "=", employee.parent_id.id), ( "deleted", "=", False)]

            values = request.env['jtemployees.evaluation.objectives'].sudo().search(payload, 
                                                                    limit=page_size, 
                                                                    offset=start, 
                                                                    order='id desc')
            
            records_count = request.env['jtemployees.evaluation.objectives'].sudo().search_count(payload)
            
            last_page = math.ceil(records_count / page_size)
            
            results = values.read(fields=["id", "name", "employee_id"])
            
            response = {
                'status': 'success',
                'data': results,
                'current_page': page,
                'last_page': last_page
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/evaluations/objectives/create', type='http', auth='none', methods=['POST'], csrf=False)
    def create_objectives(self):
        try:
            params         = request.get_json_data()
            employee_id    = params['employee_id']
            name           = params['name']
            auth_user      = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            if employee_id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 14,'message': 'You do not have permission for this employee'})
            
            payload = {
                "name": name,
                "employee_id": employee_id
            }
        
            objective = request.env['jtemployees.evaluation.objectives'].with_user(user).sudo().create(
                payload
            )
            return self.response_json({'status': 'success', 'objective': objective.read(fields=["id", "name", "employee_id"])})
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    @http.route('/jt_api/hr/evaluations/objectives/delete', type='http', auth='none', methods=['POST'], csrf=False)
    def delete_objectives(self):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            params        = request.get_json_data()
            item_id       = params['item_id']
            
            employee   = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            objective = request.env['jtemployees.evaluation.objectives'].sudo().search([('id', '=', item_id)], limit=1)
            
            if objective.employee_id.id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 14,'message': 'You do not have permission for this employee'})
            
            objective.unlink()
            
            return self.response_json({'status': 'success'})
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
    
    @http.route('/jt_api/hr/evaluations/objectives/edit', type='http', auth='none', methods=['POST'], csrf=False)
    def edit_objectives(self):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            params        = request.get_json_data()
            item_id       = params['item_id']
            name          = params['name']
            
            employee   = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            objective = request.env['jtemployees.evaluation.objectives'].sudo().search([('id', '=', item_id)], limit=1)
            
            if objective.employee_id.id not in employee.child_ids.ids:
                return self.response_json({'status': 'error', "code": 14,'message': 'You do not have permission for this employee'})
            
            objective.name = name
            
            return self.response_json({'status': 'success', 'objective': objective.read(fields=["id", "name", "employee_id"])})
        except (ValidationError, UserError) as e:
            return self.response_json({
                'status': 'error',
                'code':4,
                'message': str(e)
            })
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
    
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
        return request.env['jtapi.users'].sudo().auth_user(token, ['hr'])
