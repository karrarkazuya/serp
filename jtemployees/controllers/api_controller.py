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

class hr_controller(http.Controller):

    # team
    @http.route('/jt_api/hr/profile', type='http', auth='none', methods=['GET'], csrf=False)
    def employee_profile(self,  **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            #employee = user.employee_id
            #if not employee:
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            result = False
            if employee:
                # Prepare the employee image
                employee_image = employee.image_128  # Use the appropriate image field (e.g., image_1920)
                if employee_image:
                    # Convert binary image to base64
                    employee_image_base64 = "data:image/png;base64," + employee_image.decode()
                else:
                    employee_image_base64 = None  # Fallback if no image is available
                    
                location_checkin_area = False
                
                if employee.jt_location_area and employee.jt_location_area.area_data:
                    location_checkin_area = {
                        "name": employee.jt_location_area.name,
                        "coordinates": employee.jt_location_area.area_data,
                    }
                    
                location_checkout_area = False
                
                if employee.jt_location_area_checkout and employee.jt_location_area_checkout.area_data:
                    location_checkout_area = {
                        "name": employee.jt_location_area_checkout.name,
                        "coordinates": employee.jt_location_area_checkout.area_data,
                    }
                    
                requests_count = request.env['jtemployees.requests'].sudo().search_count([( "employee_id", "in", employee.child_ids.ids), ('manager_approved', '=', 'pending'), ("deleted", "=", False)])
                
                has_team = employee.jt_can_set_sub_schedule
                
                result = {
                    'id':  employee.id,
                    'name':  employee.name,
                    'full_name':  employee.full_name,
                    'barcode':  employee.barcode,
                    'pin':  employee.pin,
                    'image':  employee_image_base64,
                    'family_name': employee.family_name,
                    'department': employee.department_id.read(fields=['id', 'name']),
                    'parent':  employee.parent_id.read(fields=['id', 'name', 'job_title']),
                    'has_team': has_team,
                    'employees_requests_count': requests_count,
                    'current_allocations': {
                        'leave_days': employee.jt_leavedays,
                        'timeoffs': employee.jt_timeoff,
                        'timeoffs_readable': employee.jt_timeoff_readable,
                        'points': 0,
                        'limit_timeoff': employee.company_id.jt_minimum_timeoff_request_minutes
                    },
                    'location_checkin': employee.jt_has_location_checkin,
                    'location_checkin_area': location_checkin_area,
                    'location_checkout_area': location_checkout_area,
                    'attendance_info': employee.attendance_info(),
                    'extra_salary_allocations': employee.extra_allocations.read(fields=['id', 'name', 'amount']),
                    'position': employee.job_id.name,
                    'title': employee.job_title,
                    'working_schedule': employee.working_schedule(),
                    'badge_id': employee.barcode,
                    'join_date': employee.jt_join_date
                }
                
            
            response = {
                'status': 'success',
                    'data': result
                }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
    
    @http.route('/jt_api/hr/announcement_wall', type='http', auth='none', methods=['GET'], csrf=False)
    def employee_announcement_wall(self,  **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            page = kw.get('page')
            
            if not page:
                page = 1
            else:
                page = int(page)
                
            if page < 1:
                page = 1
            
            page_size = 25
            start = (page - 1) * page_size
            end = start + page_size
            
            search_domain = [
                ('deleted', '=', False),
                ('expires', '>', datetime.now()),
                ('departments', 'in', [employee.department_id.id])
            ]
            
            records = request.env['jtemployees.wall'].sudo().search(search_domain,
                                                                                limit=page_size, 
                                                                                offset=start, 
                                                                                order='id desc')
            records_count = request.env['jtemployees.wall'].sudo().search_count(search_domain)
            
            last_page = math.ceil(records_count / page_size)
            
            results = []
            
            for item in records:
                image_base64 = None
                if item.image:
                    # Prepare the employee image
                    image = item.image  # Use the appropriate image field (e.g., image_1920)
                    if image:
                        # Convert binary image to base64
                        image_base64 = "data:image/png;base64," + image.decode()
                results.append({
                    "id": item.id,
                    "name": item.name,
                    "details": item.details,
                    "image":   image_base64,
                    "created_at": item.create_date,
                })
                    
                
            response = {
                'status': 'success',
                    'data': results,
                    'current_page': page,
                    'last_page': last_page
                }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
    
    # payrolls
    @http.route('/jt_api/hr/payrolls/index/page/<int:page>', type='http', auth='none', methods=['GET'], csrf=False)
    def payrolls_history(self, page=1, **kw):
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            if page < 1:
                page = 1
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)

            page_size = 100
            start = (page - 1) * page_size
            end = start + page_size

            requests = request.env['jtemployees.payrolls.slips'].sudo().search([( "employee", "=", employee.id), ("approved", "=", True), ("deleted", "=", False)], 
                                                                        limit=page_size, 
                                                                        offset=start, 
                                                                        order='id desc')
            
            requests_count = request.env['jtemployees.payrolls.slips'].sudo().search_count([( "employee", "=", employee.id), ("approved", "=", True), ("deleted", "=", False)])
            last_page = math.ceil(requests_count / page_size)
            
            result = []
            
            for item in requests:
                details = []
                json_shortage_data = []
                for detail in item.details:
                    json_data = []
                    if detail.json_details != "":
                        json_data = json.loads(detail.json_details.replace("\'", "\"")) # to be replaced with sub.details in future
                    details.append({
                        "id": detail.id,
                        "title": detail.name,
                        "info": json_data,
                        "amount": detail.amount,
                    })
                result.append({
                    "id": item.id,
                    "details": details,
                    "shortage_data": json_shortage_data,
                    "date_from": item.date_selected_from,
                    "date_to": item.date_selected_to,
                    "salary_amount": item.total_amount,
                    "created_at": item.create_date,
                })
            
            #results = requests.read(fields=["id", "date_selected_from", "date_selected_to", "details", "json_data", "total_amount", "create_date"])
            
            response = {
                'status': 'success',
                'data': result,
                'current_page': page,
                'last_page': last_page
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})

    # team
    @http.route('/jt_api/hr/team/index', type='http', auth='none', methods=['GET'], csrf=False)
    def team_history(self,  **kw):
        
        try:
            auth_user = self.get_user(request)
            
            if not auth_user:
                return self.response_json({'status': 'error', 'code': 1, 'message': 'auth required'})
            request.env = request.env(user=auth_user.user_id.id)
            
            user = auth_user.user_id
            
            employee = request.env['hr.employee'].sudo().search([('id', '=', auth_user.employee.id)], limit=1)
            
            sub_employees = []
            
            childs = self.get_all_childs(request=request, employee_id=employee.id)
            if len(childs) > 0:
                child_employees = request.env['hr.employee'].sudo().browse(childs)
                for sub_employee in child_employees:
                    # Prepare the employee image
                    sub_employee_image = sub_employee.image_128  # Use the appropriate image field (e.g., image_1920)
                    if sub_employee_image:
                        # Convert binary image to base64
                        sub_employee_image_base64 = "data:image/png;base64," + sub_employee_image.decode()
                    else:
                        sub_employee_image_base64 = None  # Fallback if no image is available
                        
                    location_checkin = False
                    location_checkout = False
                    if sub_employee.jt_has_location_checkin:
                        location = sub_employee.jt_location_area
                        if location and location.area_data:
                            location_checkin = {
                                "id": location.id,
                                "title": location.name,
                                "area_data": location.area_data
                            }
                            
                        location = sub_employee.jt_location_area_checkout
                        if location and location.area_data:
                            location_checkout = {
                                "id": location.id,
                                "title": location.name,
                                "area_data": location.area_data
                            }
                        
                    sub_employees.append({
                        'id': sub_employee.id,
                        'name': sub_employee.name,
                        'parent': sub_employee.parent_id.name,
                        'image': sub_employee_image_base64,
                        'job_position': sub_employee.job_id.name,
                        'job_title': sub_employee.job_title,
                        'has_location_checkin': sub_employee.jt_has_location_checkin,
                        'location_checkin': location_checkin,
                        'location_checkout': location_checkout,
                        'current_allocations': {
                            'leave_days': sub_employee.jt_leavedays,
                            'timeoff': sub_employee.jt_timeoff,
                            'points': sub_employee.jt_current_points
                        },
                        'working_schedule': sub_employee.working_schedule(),
                        'pending_data': {
                            'requests': request.env['jtemployees.requests'].sudo().search_count([('employee_id', '=', sub_employee.id), ('manager_approved', '=', 'pending')]),
                            'evaluations': request.env['jtemployees.points'].sudo().search_count([('employee_id', '=', sub_employee.id), ('submitted', '=', False)])
                        }
                    })

            response = {
                'status': 'success',
                'data': sub_employees
            }
            
            return self.response_json(response)
        except:
            return self.response_json({'status': 'error', 'code':2, 'message': 'Unable to process the request'})
        
    def get_all_childs(self, request, employee_id, visited=None):
        if visited is None:
            visited = set()
        
        if employee_id in visited:
            return []
        visited.add(employee_id)
        
        ids = []
        employees = request.env['hr.employee'].sudo().search([('parent_id', '=', employee_id)])
        for item in employees:
            ids.append(item.id)
            new_ids = self.get_all_childs(request=request, employee_id=item.id, visited=visited)
            for item2 in new_ids:
                ids.append(item2)
        return ids
    
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
