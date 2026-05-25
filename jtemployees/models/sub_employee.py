# -*- coding: utf-8 -*-

from odoo import models, fields, exceptions


class subs_model(models.Model):
    _name = 'jtemployees.subs'
    _description = 'Sub Employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    parent_employee_ids          = fields.Many2many('hr.employee', string='Parent Employees')
    employee_id                  = fields.Many2one('hr.employee', string='Employee', tracking=True)
    resource_calendar_id         = fields.Many2one('resource.calendar', string='Working Schedule', tracking=True)
    location_area                = fields.Many2one('jtemployees.location.areas', string='Location Area (checkin)', tracking=True)
    location_area_checkout       = fields.Many2one('jtemployees.location.areas', string='Location Area (checkout)', tracking=True)
    has_location_checkin         = fields.Boolean(string='Has Location Check in', default=False, tracking=True)
    reflect_on_children          = fields.Boolean(string='Reflect on children', help="When checked, any changes made will also be applied to employees below the selected employee", default=False, tracking=True)
    childs                       = fields.One2many('jtemployees.subs.childs', inverse_name="parent_id", string='Affected employees')


    def read(self, fields=None, load='_classic_read'):
        if not self.env.user.employee_id.jt_can_set_sub_schedule:
            return []
        return super().read(fields, load)
    
    
    def write(self, values):
        previous_working_schedule = self.resource_calendar_id.id if self.resource_calendar_id else False
        item = super().write(values)
        
        if self.reflect_on_children:
            employee_ids = self._get_all_subs()
        else:
            employee_ids = [self.employee_id.id]
            
        sub_employee = self.env['hr.employee'].sudo().search([('id', 'in', employee_ids)])
        if not sub_employee:
            return item
            
        if self.reflect_on_children:
            # sync childs for view
            self.env['jtemployees.subs.childs'].sudo().search([('parent_id', '=', self.id)]).unlink()
            
            for item in sub_employee:
                if item.id != self.employee_id.id:
                    self.env['jtemployees.subs.childs'].sudo().create(
                        {
                            "parent_id": self.id,
                            "employee_id": item.id,
                            "resource_calendar_id": self.resource_calendar_id.id,
                            "location_area": self.location_area.id,
                            "location_area_checkout": self.location_area_checkout.id,
                        }
                    )
                    
                    self.env['jtemployees.subs'].sudo().search([('employee_id', '=', item.id)]).write(
                        {
                            "resource_calendar_id": self.resource_calendar_id.id,
                            "location_area": self.location_area.id,
                            "location_area_checkout": self.location_area_checkout.id,
                        }
                    )
                
        
        employee_update_payload = {}
        
        if 'resource_calendar_id' in values and (not previous_working_schedule or previous_working_schedule != values['resource_calendar_id']):
            employee_update_payload['resource_calendar_id'] = self.resource_calendar_id.id
            
        if 'has_location_checkin' in values and self.has_location_checkin != values['has_location_checkin']:
            employee_update_payload['jt_has_location_checkin'] = self.has_location_checkin
            
        if 'location_area' in values and self.location_area.id != values['location_area']:
            employee_update_payload['jt_location_area'] = self.location_area.id
            
        if 'location_area_checkout' in values and self.location_area_checkout.id != values['location_area_checkout']:
            employee_update_payload['location_area_checkout'] = self.location_area_checkout.id
        
        if employee_update_payload != {}:
            employee = self.env['hr.employee'].sudo().search([('id', '=', self.employee_id.id)], limit=1)
            if not employee:
                return item
            employee.write_of_sub(employee_update_payload)
        return item
    
    
    def _get_all_subs(self, visited=None):
        if self.employee_id:
            if visited is None:
                visited = set()

            if not self.employee_id or self.employee_id.id in visited:
                return []
            visited.add(self.employee_id.id)
            ids = [self.employee_id.id]
            subs = self.env['jtemployees.subs'].sudo().search([('parent_employee_ids', 'in', [self.employee_id.id])])
            for item in subs:
                if item.employee_id:
                    sub_ids = item._get_all_subs(visited)
                    for sub_item in sub_ids:
                        ids.append(sub_item)
            return ids
        else:
            return []
            
    
    def sync(self, employees):
        if not employees.ids:
            if not isinstance(employees, list):
                employees = [employees]
        for employee in employees:
            sub_employee = self.env['jtemployees.subs'].sudo().search([
                (
                    'employee_id', '=', employee.id
                )
            ], limit=1)
            calendar = employee.resource_calendar_id.id if employee.resource_calendar_id else False
            parents = self.get_parents(employee.id)
            if not sub_employee:
                self.env['jtemployees.subs'].sudo().create(
                    {
                        "parent_employee_ids": [(6, 0, parents)],
                        "employee_id": employee.id,
                        "resource_calendar_id": calendar,
                        "location_area": employee.jt_location_area.id,
                        "location_area_checkout": employee.jt_location_area_checkout.id,
                    }
                )
            else:
                sub_employee.parent_employee_ids    = [(6, 0, parents)]
                sub_employee.resource_calendar_id   = calendar
                sub_employee.has_location_checkin   = employee.jt_has_location_checkin
                sub_employee.location_area          = employee.jt_location_area.id
                sub_employee.location_area_checkout = employee.jt_location_area_checkout.id
        
    
    def sync_all(self):
        employees = self.env['hr.employee'].sudo().search([])
        for employee in employees:
            
            if employee.parent_id:
                parents = self.get_parents(employee.id)
                sub_employee = self.env['jtemployees.subs'].sudo().search([
                    (
                        'employee_id', '=', employee.id
                    )
                ], limit=1)
                
                if not sub_employee:
                    sub_employee = self.env['jtemployees.subs'].sudo().create(
                        {
                            "parent_employee_ids": [(6, 0, parents)],
                            "employee_id": employee.id,
                            "resource_calendar_id": employee.resource_calendar_id.id,
                            "location_area": employee.jt_location_area.id,
                            "location_area_checkout": employee.jt_location_area_checkout.id,
                        }
                    )
                else:
                    sub_employee.parent_employee_ids = [(6, 0, parents)]
                    sub_employee.resource_calendar_id = employee.resource_calendar_id.id
                    sub_employee.has_location_checkin = employee.jt_has_location_checkin
                    sub_employee.location_area = employee.jt_location_area.id
                    sub_employee.location_area_checkout = employee.jt_location_area_checkout.id
                    
                
    def get_parents(self, employee_id, visited=None):
        ignore_first = False
        if visited is None:
            visited = set()
            ignore_first = True
        
        if employee_id in visited:
            return []
        visited.add(employee_id)
        
        ids = []
        employees = self.env['hr.employee'].sudo().search([('id', '=', employee_id)])
        for item in employees:
            if not ignore_first:
                ids.append(item.id)
            ids.extend(self.get_parents(item.parent_id.id, visited))
        
        return ids

class subs_childs_model(models.Model):
    _name = 'jtemployees.subs.childs'
    _description = 'Sub Employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    parent_id                    = fields.Many2one('jtemployees.subs', string='Parent', tracking=True)
    employee_id                  = fields.Many2one('hr.employee', string='Employee', tracking=True)
    resource_calendar_id         = fields.Many2one('resource.calendar', string='Working Schedule', tracking=True)
    location_area                = fields.Many2one('jtemployees.location.areas', string='Location Area (checkin)', tracking=True)
    location_area_checkout       = fields.Many2one('jtemployees.location.areas', string='Location Area (checkout)', tracking=True)
    has_location_checkin         = fields.Boolean(string='Has Location Check in', default=False, tracking=True)
    